<?php

namespace App\Services\Tennis;

use App\Models\TennisMatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Imports historical ATP/WTA results. The primary source is Tennis-Data.co.uk
 * (.xlsx, parsed natively via ZipArchive/XMLReader — no extra dependency), but
 * a Sackmann-style CSV (local file or URL) is detected and handled too. Player
 * names are folded to a canonical form so history links to live fixtures.
 */
class TennisDataImporter
{
    /** @return int number of imported or updated matches */
    public function import(string $tour, int $year): int
    {
        $template = config('services.tennis_data.' . strtolower($tour) . '_url');
        if (blank($template)) {
            throw new RuntimeException("TENNIS_{$tour}_SOURCE_URL is not configured.");
        }

        $source = str_replace('{year}', (string) $year, (string) $template);
        $rows   = $this->readRows($source, $tour, $year);
        if ($rows === []) {
            throw new RuntimeException("Tennis source returned no readable rows for {$tour} {$year}.");
        }

        $count = 0;
        foreach ($rows as $row) {
            $winner = TennisNameNormalizer::canonical($row['winner'] ?? ($row['winner_name'] ?? ''));
            $loser  = TennisNameNormalizer::canonical($row['loser'] ?? ($row['loser_name'] ?? ''));
            $date   = $this->date($row['date'] ?? ($row['tourney_date'] ?? null));
            if ($winner === '' || $loser === '' || $date === null) continue;

            $tournament = $row['tournament'] ?? ($row['tourney_name'] ?? null);
            $round      = $row['round'] ?? null;
            $sourceKey  = sha1(implode('|', [strtoupper($tour), $date->toDateString(), $winner, $loser, (string) $tournament, (string) $round]));

            TennisMatch::updateOrCreate(
                ['source' => 'tennisdata', 'source_key' => $sourceKey],
                [
                    'tour' => strtoupper($tour), 'tournament' => $tournament,
                    'surface' => $this->surface($row['surface'] ?? null), 'match_date' => $date,
                    'round' => $round, 'best_of' => $this->integer($row['best of'] ?? ($row['best_of'] ?? null)),
                    'player_one' => $winner, 'player_two' => $loser, 'winner' => $winner,
                    'player_one_rank' => $this->integer($row['wrank'] ?? ($row['winner_rank'] ?? null)),
                    'player_two_rank' => $this->integer($row['lrank'] ?? ($row['loser_rank'] ?? null)),
                    'score' => $row['score'] ?? $this->scoreFromSets($row), 'status' => 'completed',
                    'stats' => $this->statistics($row),
                ],
            );
            $count++;
        }

        return $count;
    }

    /** @return array<int, array<string, string|null>> */
    private function readRows(string $source, string $tour, int $year): array
    {
        if (preg_match('#^https?://#i', $source)) {
            $response = Http::timeout(120)->accept('*/*')->get($source);
            if ($response->failed()) {
                throw new RuntimeException("Tennis source returned HTTP {$response->status()} for {$tour} {$year}.");
            }
            $body = $response->body();
        } else {
            if (! is_file($source) || ! is_readable($source)) {
                throw new RuntimeException("Tennis file not found for {$tour} {$year}: {$source}.");
            }
            $body = (string) file_get_contents($source);
        }

        // .xlsx is a ZIP archive ("PK\x03\x04"); anything else is treated as CSV.
        return str_starts_with($body, "PK\x03\x04")
            ? $this->parseXlsx($body, $tour, $year)
            : $this->parseCsv($body);
    }

    /** @return array<int, array<string, string|null>> */
    private function parseXlsx(string $body, string $tour, int $year): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'tdx');
        file_put_contents($tmp, $body);
        try {
            $zip = new \ZipArchive();
            if ($zip->open($tmp) !== true) {
                throw new RuntimeException("Unreadable xlsx for {$tour} {$year}.");
            }
            $shared   = $this->sharedStrings((string) $zip->getFromName('xl/sharedStrings.xml'));
            $sheetXml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            return $this->sheetRows($sheetXml, $shared);
        } finally {
            @unlink($tmp);
        }
    }

    /** @return array<int, string> shared string table in index order */
    private function sharedStrings(string $xml): array
    {
        if ($xml === '') return [];
        $out = [];
        $reader = new \XMLReader();
        if (! $reader->XML($xml)) return [];
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'si') {
                $out[] = html_entity_decode(strip_tags($reader->readInnerXml()), ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }
        $reader->close();
        return $out;
    }

    /** @return array<int, array<string, string|null>> rows keyed by lower-cased header */
    private function sheetRows(string $xml, array $shared): array
    {
        if ($xml === '') return [];
        $reader = new \XMLReader();
        if (! $reader->XML($xml)) return [];
        $doc = new \DOMDocument();
        $rows = [];
        $header = null;

        while ($reader->read() && $reader->localName !== 'row');
        while ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'row') {
            $node = $reader->expand($doc);
            $cells = [];
            if ($node instanceof \DOMElement) {
                foreach ($node->getElementsByTagName('c') as $c) {
                    $col = preg_replace('/\d+/', '', $c->getAttribute('r'));
                    $cells[$col] = $this->cellValue($c, $shared);
                }
            }
            if ($header === null) {
                $header = array_map(fn ($v) => strtolower(trim((string) $v)), $cells);
            } else {
                $assoc = [];
                foreach ($header as $col => $name) {
                    if ($name === '') continue;
                    $assoc[$name] = $cells[$col] ?? null;
                }
                if (array_filter($assoc, fn ($v) => $v !== null && $v !== '')) $rows[] = $assoc;
            }
            $reader->next('row');
        }
        $reader->close();
        return $rows;
    }

    private function cellValue(\DOMElement $c, array $shared): ?string
    {
        $type = $c->getAttribute('t');
        if ($type === 'inlineStr') {
            $t = $c->getElementsByTagName('t')->item(0);
            return $t?->textContent;
        }
        $v = $c->getElementsByTagName('v')->item(0);
        if (! $v) return null;
        return $type === 's' ? ($shared[(int) $v->textContent] ?? null) : $v->textContent;
    }

    /** @return array<int, array<string, string|null>> */
    private function parseCsv(string $csv): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv);
        rewind($stream);
        $headers = fgetcsv($stream);
        if (! is_array($headers)) return [];
        $headers = array_map(fn ($v) => strtolower(trim((string) $v)), $headers);
        $rows = [];
        while (($values = fgetcsv($stream)) !== false) {
            if (count($values) !== count($headers)) continue;
            $rows[] = array_combine($headers, $values);
        }
        fclose($stream);
        return $rows;
    }

    /** Accepts an Excel serial number, a Ymd string, or a d/m/Y string. */
    private function date(mixed $value): ?CarbonImmutable
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (preg_match('/^\d{1,6}$/', $value) && ! preg_match('/^\d{8}$/', $value)) {
            $serial = (int) $value;
            return $serial > 0 ? CarbonImmutable::create(1899, 12, 30)->addDays($serial) : null;
        }
        if (preg_match('/^\d{8}$/', $value)) {
            try { return CarbonImmutable::createFromFormat('Ymd', $value)->startOfDay(); } catch (\Throwable) { return null; }
        }
        foreach (['d/m/Y', 'd/m/y', 'Y-m-d'] as $format) {
            try { return CarbonImmutable::createFromFormat($format, $value)->startOfDay(); } catch (\Throwable) { continue; }
        }
        return null;
    }

    private function scoreFromSets(array $row): ?string
    {
        $sets = [];
        for ($i = 1; $i <= 5; $i++) {
            $w = $row["w{$i}"] ?? null;
            $l = $row["l{$i}"] ?? null;
            if ($w === null || $w === '' || $l === null || $l === '') continue;
            $sets[] = ((int) $w) . '-' . ((int) $l);
        }
        $score = implode(', ', $sets);
        $comment = trim((string) ($row['comment'] ?? ''));
        if ($comment !== '' && strtolower($comment) !== 'completed') {
            $score = trim($score . " ({$comment})");
        }
        return $score !== '' ? $score : null;
    }

    private function integer(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function surface(mixed $value): ?string
    {
        $surface = strtolower(trim((string) $value));
        return in_array($surface, ['hard', 'clay', 'grass', 'carpet'], true) ? ucfirst($surface) : null;
    }

    private function statistics(array $row): array
    {
        $keys = [
            'b365w', 'b365l', 'psw', 'psl', 'maxw', 'maxl', 'avgw', 'avgl', 'wpts', 'lpts', 'wsets', 'lsets', 'comment',
            'w_ace', 'w_df', 'w_svpt', 'w_1stin', 'w_1stwon', 'w_2ndwon', 'w_svgms', 'w_bpsaved', 'w_bpfaced',
            'l_ace', 'l_df', 'l_svpt', 'l_1stin', 'l_1stwon', 'l_2ndwon', 'l_svgms', 'l_bpsaved', 'l_bpfaced', 'minutes',
        ];
        return collect($row)->only($keys)->filter(fn ($v) => $v !== null && $v !== '')->all();
    }
}
