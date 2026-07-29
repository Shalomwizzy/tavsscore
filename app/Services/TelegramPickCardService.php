<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/** Renders one complete, data-led Telegram pick message as a premium JPEG. */
class TelegramPickCardService
{
    /** @param array<int, array<string, mixed>> $picks */
    public function render(string $title, array $picks): ?string
    {
        if (! function_exists('imagecreatetruecolor') || empty($picks) || ! ($font = $this->fontPath())) return null;

        try {
            $items = array_values(array_filter(array_map(fn (array $pick) => $this->normalise($pick), array_slice($picks, 0, 8))));
            if (empty($items)) return null;

            $width = 1200; $height = 472 + (count($items) * 142); $accent = $this->accentFor($title);
            $image = imagecreatetruecolor($width, $height); imagealphablending($image, true);
            $this->background($image, $width, $height); $this->glow($image, 1065, 65, 300, $accent);

            $this->text($image, $font, 31, 58, 66, 'TavsScore', [255, 255, 255], true);
            $this->text($image, $font, 14, 58, 96, 'DATA-LED FOOTBALL INTELLIGENCE', [155, 176, 196], true);
            $this->text($image, $font, 40, 58, 172, $this->shorten($title, 38), [255, 255, 255], true);
            $this->text($image, $font, 17, 58, 208, now('Africa/Lagos')->format('l, d M Y · H:i').' Lagos', [183, 201, 215]);
            $this->line($image, 58, 237, 1142, 237, [41, 64, 87]);

            foreach ($items as $index => $item) {
                $y = 300 + ($index * 142);
                $this->roundedRect($image, 58, $y, 1142, $y + 118, 20, [17, 29, 45]);
                $this->roundedRect($image, 58, $y, 67, $y + 118, 5, $accent);
                $this->text($image, $font, 14, 96, $y + 35, 'PICK '.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), [142, 164, 185], true);
                $this->text($image, $font, 24, 96, $y + 72, $item['match'], [255, 255, 255], true);
                $this->text($image, $font, 16, 96, $y + 101, $item['league'], [152, 173, 192]);
                $this->text($image, $font, 14, 720, $y + 42, 'MODEL CALL', [151, 172, 192], true);
                $this->text($image, $font, 21, 720, $y + 79, $item['tip'], $accent, true);
                $this->roundedRect($image, 1025, $y + 28, 1107, $y + 84, 28, $accent);
                $this->centered($image, $font, 18, 1066, $y + 64, $item['confidence'], [7, 20, 29], true);
            }

            $footerY = $height - 76; $this->line($image, 58, $footerY, 1142, $footerY, [41, 64, 87]);
            $this->text($image, $font, 15, 58, $footerY + 40, 'Selections are model analysis, not guarantees. Verify final information before any decision.', [155, 176, 196]);
            ob_start(); imagejpeg($image, null, 90); $binary = (string) ob_get_clean(); imagedestroy($image);
            if ($binary === '' || ! str_starts_with($binary, "\xFF\xD8\xFF")) return null;
            $path = 'telegram/picks/'.now('Africa/Lagos')->format('Y-m-d').'/'.uniqid('pick-', true).'.jpg';
            Storage::disk('public')->put($path, $binary); return $path;
        } catch (\Throwable $e) {
            Log::warning('Telegram pick-card render failed', ['message' => $e->getMessage()]); return null;
        }
    }

    /** @param array<string, mixed> $pick @return array<string, string>|null */
    private function normalise(array $pick): ?array
    {
        $match = trim((string) ($pick['match'] ?? '')); if ($match === '') return null;
        $tip = trim((string) ($pick['tip'] ?? $pick['line'] ?? $pick['label'] ?? ''));
        if ($tip === '' && isset($pick['team'], $pick['market'])) $tip = trim((string) $pick['team']).' · Under '.(string) $pick['market'].' goals';
        if ($tip === '' && isset($pick['scores']) && is_array($pick['scores'])) $tip = 'Top score: '.(string) (($pick['scores'][0] ?? [])['score'] ?? 'Model scoreline');
        if ($tip === '' && filled($pick['player'] ?? null)) $tip = (string) $pick['player'].' — Anytime goalscorer';
        if ($tip === '') $tip = 'Model selection';
        $confidence = $pick['confidence'] ?? $pick['prob'] ?? '';
        $confidence = $confidence === '' ? 'DATA' : (is_numeric($confidence) ? rtrim(rtrim(number_format((float) $confidence, 1, '.', ''), '0'), '.').'%' : (string) $confidence);
        return ['match' => $this->shorten($match, 43), 'league' => $this->shorten((string) ($pick['league'] ?? 'Football intelligence'), 46), 'tip' => $this->shorten($tip, 34), 'confidence' => $confidence];
    }

    private function background(\GdImage $image, int $width, int $height): void
    {
        for ($y = 0; $y < $height; $y++) { $r = $y / max(1, $height - 1); imagefilledrectangle($image, 0, $y, $width, $y, $this->color($image, [(int) round(8 + 12 * $r), (int) round(17 + 18 * $r), (int) round(31 + 25 * $r)])); }
    }
    /** @param array{0:int,1:int,2:int} $accent */
    private function glow(\GdImage $image, int $x, int $y, int $radius, array $accent): void
    {
        for ($r = $radius; $r > 0; $r -= 12) imagefilledellipse($image, $x, $y, $r * 2, $r * 2, imagecolorallocatealpha($image, $accent[0], $accent[1], $accent[2], 118));
    }
    /** @param array{0:int,1:int,2:int} $color */
    private function text(\GdImage $image, string $font, int $size, int $x, int $y, string $text, array $color, bool $bold = false): void
    {
        $fill = $this->color($image, $color); imagettftext($image, $size, 0, $x, $y, $fill, $font, $text); if ($bold) imagettftext($image, $size, 0, $x + 1, $y, $fill, $font, $text);
    }
    /** @param array{0:int,1:int,2:int} $color */
    private function centered(\GdImage $image, string $font, int $size, int $x, int $y, string $text, array $color, bool $bold = false): void
    {
        $box = imagettfbbox($size, 0, $font, $text); $this->text($image, $font, $size, $x - (int) round(($box[2] - $box[0]) / 2), $y, $text, $color, $bold);
    }
    /** @param array{0:int,1:int,2:int} $color */
    private function roundedRect(\GdImage $image, int $x1, int $y1, int $x2, int $y2, int $radius, array $color): void
    {
        $fill = $this->color($image, $color); imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $fill); imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $fill); foreach ([[$x1 + $radius, $y1 + $radius], [$x2 - $radius, $y1 + $radius], [$x1 + $radius, $y2 - $radius], [$x2 - $radius, $y2 - $radius]] as [$x, $y]) imagefilledellipse($image, $x, $y, $radius * 2, $radius * 2, $fill);
    }
    /** @param array{0:int,1:int,2:int} $color */
    private function line(\GdImage $image, int $x1, int $y1, int $x2, int $y2, array $color): void { imageline($image, $x1, $y1, $x2, $y2, $this->color($image, $color)); }
    /** @param array{0:int,1:int,2:int} $color */
    private function color(\GdImage $image, array $color): int { return imagecolorallocate($image, $color[0], $color[1], $color[2]); }
    /** @return array{0:int,1:int,2:int} */
    private function accentFor(string $title): array
    {
        $title = strtolower($title); return match (true) { str_contains($title, 'corner') => [251,191,36], str_contains($title, 'over') => [251,146,60], str_contains($title, 'under') => [96,165,250], str_contains($title, 'draw') => [192,132,252], str_contains($title, 'handicap') => [56,189,248], str_contains($title, 'score') => [244,114,182], default => [56,230,197] };
    }
    private function fontPath(): ?string
    {
        foreach (array_filter([config('services.telegram.card_font'), resource_path('fonts/DejaVuSans.ttf'), '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/usr/share/fonts/dejavu/DejaVuSans.ttf', '/System/Library/Fonts/Supplemental/Verdana.ttf']) as $font) if (is_file($font) && is_readable($font)) return $font;
        return null;
    }
    private function shorten(string $value, int $limit): string { $value = trim(preg_replace('/\s+/', ' ', $value) ?: ''); return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit - 1).'…' : $value; }
}
