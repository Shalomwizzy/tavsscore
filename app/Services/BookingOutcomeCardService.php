<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Creates a share-ready result card for settled booking codes. */
class BookingOutcomeCardService
{
    public function render(string $platform, string $code, string $note, bool $won, ?float $totalOdds = null): ?string
    {
        if (! function_exists('imagecreatetruecolor') || ! ($font = $this->fontPath())) {
            return null;
        }

        $width = 1200;
        $height = 630;
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, true);

        try {
            $background = imagecolorallocate($image, 13, 20, 33);
            $panel = imagecolorallocate($image, 23, 33, 50);
            $muted = imagecolorallocate($image, 163, 174, 192);
            $white = imagecolorallocate($image, 248, 250, 252);
            $accent = $won ? imagecolorallocate($image, 34, 197, 94) : imagecolorallocate($image, 239, 68, 68);
            $accentDark = $won ? imagecolorallocate($image, 21, 128, 61) : imagecolorallocate($image, 185, 28, 28);

            imagefill($image, 0, 0, $background);
            imagefilledrectangle($image, 0, 0, $width, 9, $accent);
            imagefilledrectangle($image, 0, 9, $width, 130, $panel);
            imagefilledrectangle($image, 0, 130, $width, 134, $accentDark);

            $this->text($image, $font, 'TAVSSCORE', 29, 52, 67, $white);
            $this->text($image, $font, 'BOOKING CODE RESULT', 16, 52, 98, $muted);
            $this->textRight($image, $font, now('Africa/Lagos')->format('d M Y • H:i'), 16, 1145, 72, $muted);

            $status = $won ? 'BOOKING CODE WON' : 'BOOKING CODE LOST';
            $this->text($image, $font, $status, 42, 84, 241, $accent);
            $this->text($image, $font, $won ? 'A winning ticket. Well played.' : 'This ticket did not land. Results are tracked openly.', 19, 85, 280, $muted);

            imagefilledrectangle($image, 74, 330, 1126, 484, $panel);
            $this->text($image, $font, 'BOOKING CODE', 16, 110, 370, $muted);
            $this->text($image, $font, strtoupper($code), 46, 109, 432, $white);
            $this->text($image, $font, strtoupper($platform), 17, 739, 370, $muted);
            $this->text($image, $font, $totalOdds !== null ? number_format($totalOdds, 2).' ODDS' : 'RESULT VERIFIED', 27, 739, 425, $white);

            $summary = trim($note) !== '' ? $note : 'See the booking-code history for full ticket details.';
            $lines = $this->wrap($summary, 60, 2);
            $this->text($image, $font, $lines[0] ?? '', 18, 76, 534, $muted);
            if (isset($lines[1])) {
                $this->text($image, $font, $lines[1], 18, 76, 564, $muted);
            }

            $this->text($image, $font, 'tavsscore.com  •  Data-led football analysis', 15, 76, 598, $muted);

            ob_start();
            imagejpeg($image, null, 91);
            $binary = (string) ob_get_clean();
        } finally {
            imagedestroy($image);
        }

        $path = 'telegram/outcomes/booking-'.Str::lower($won ? 'won' : 'lost').'-'.Str::uuid().'.jpg';
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    /** @param resource|\GdImage $image */
    private function text($image, string $font, string $text, int $size, int $x, int $y, int $color): void
    {
        imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
    }

    /** @param resource|\GdImage $image */
    private function textRight($image, string $font, string $text, int $size, int $right, int $y, int $color): void
    {
        $box = imagettfbbox($size, 0, $font, $text);
        $width = $box[2] - $box[0];
        $this->text($image, $font, $text, $size, $right - $width, $y, $color);
    }

    /** @return array<int, string> */
    private function wrap(string $text, int $limit, int $maxLines): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = trim($line.' '.$word);
            if (mb_strlen($candidate) > $limit && $line !== '') {
                $lines[] = $line;
                $line = $word;
                if (count($lines) === $maxLines) {
                    break;
                }
                continue;
            }
            $line = $candidate;
        }

        if ($line !== '' && count($lines) < $maxLines) {
            $lines[] = $line;
        }

        if (count($lines) === $maxLines && count($words) > 0) {
            $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1], '.'). '…';
        }

        return $lines;
    }

    private function fontPath(): ?string
    {
        foreach (array_filter([
            config('services.telegram.card_font'),
            resource_path('fonts/DejaVuSans.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            '/System/Library/Fonts/Supplemental/Verdana.ttf',
        ]) as $font) {
            if (is_file($font) && is_readable($font)) {
                return $font;
            }
        }

        return null;
    }
}
