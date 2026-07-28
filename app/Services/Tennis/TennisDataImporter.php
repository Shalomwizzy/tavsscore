<?php

namespace App\Services\Tennis;

use App\Models\TennisMatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Imports standard Jeff Sackmann-style ATP/WTA match CSV files idempotently. */
class TennisDataImporter
{
    /** @return int number of imported or updated matches */
    public function import(string $tour, int $year): int
    {
        $key = strtolower($tour) . '_url';
        $template = config("services.tennis_data.{$key}");
        if (blank($template)) {
            throw new RuntimeException("TENNIS_{$tour}_SOURCE_URL is not configured.");
        }

        // The source may be a remote URL or a self-hosted local file (default) —
        // {year} is substituted either way.
        $source = str_replace('{year}', (string) $year, $template);
        $csv    = $this->read($source, $tour, $year);

        $rows = $this->rows($csv);
        if ($rows === []) {
            throw new RuntimeException("Tennis source returned no readable rows for {$tour} {$year}.");
        }

        $count = 0;
        foreach ($rows as $row) {
            $winner = trim((string) ($row['winner_name'] ?? ''));
            $loser = trim((string) ($row['loser_name'] ?? ''));
            $date = $this->date($row['tourney_date'] ?? null);
            if ($winner === '' || $loser === '' || $date === null) continue;

            $sourceKey = (string) ($row['tourney_id'] ?? '') . ':' . (string) ($row['match_num'] ?? '');
            if ($sourceKey === ':') {
                $sourceKey = sha1(implode('|', [$tour, $date->toDateString(), $winner, $loser, $row['tourney_name'] ?? '', $row['round'] ?? '']));
            }

            TennisMatch::updateOrCreate(
                ['source' => 'sackmann_csv', 'source_key' => $sourceKey],
                [
                    'tour' => strtoupper($tour), 'tournament' => $row['tourney_name'] ?? null,
                    'surface' => $this->surface($row['surface'] ?? null), 'match_date' => $date,
                    'round' => $row['round'] ?? null, 'best_of' => $this->integer($row['best_of'] ?? null),
                    'player_one' => $winner, 'player_two' => $loser, 'winner' => $winner,
                    'player_one_rank' => $this->integer($row['winner_rank'] ?? null),
                    'player_two_rank' => $this->integer($row['loser_rank'] ?? null),
                    'score' => $row['score'] ?? null, 'status' => 'completed',
                    'stats' => $this->statistics($row),
                ],
            );
            $count++;
        }

        return $count;
    }

    /** Read the CSV from a remote URL or a self-hosted local file. */
    private function read(string $source, string $tour, int $year): string
    {
        if (preg_match('#^https?://#i', $source)) {
            $response = Http::timeout(90)->accept('text/csv,text/plain,*/*')->get($source);
            if ($response->failed()) {
                throw new RuntimeException("Tennis source returned HTTP {$response->status()} for {$tour} {$year}.");
            }
            return $response->body();
        }

        if (! is_file($source) || ! is_readable($source)) {
            throw new RuntimeException("Tennis file not found for {$tour} {$year}: {$source}. Upload the CSV to storage/app/tennis/.");
        }

        return (string) file_get_contents($source);
    }

    /** @return array<int, array<string, string|null>> */
    private function rows(string $csv): array
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

    private function date(mixed $value): ?CarbonImmutable
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d{8}$/', $value)) return null;
        try { return CarbonImmutable::createFromFormat('Ymd', $value); } catch (\Throwable) { return null; }
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
        return collect($row)->only(['w_ace', 'w_df', 'w_svpt', 'w_1stin', 'w_1stwon', 'w_2ndwon', 'w_svgms', 'w_bpsaved', 'w_bpfaced', 'l_ace', 'l_df', 'l_svpt', 'l_1stin', 'l_1stwon', 'l_2ndwon', 'l_svgms', 'l_bpsaved', 'l_bpfaced', 'minutes'])->filter(fn ($v) => $v !== null && $v !== '')->all();
    }
}
