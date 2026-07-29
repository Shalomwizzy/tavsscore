<?php

namespace App\Services;

/** Applies a subtle, legible TavsScore mark to externally captured ticket images. */
class ImageWatermarkService
{
    public function stamp(string $binary): string
    {
        if (! function_exists('imagecreatefromstring') || ! ($font = $this->fontPath())) {
            return $binary;
        }

        $image = @imagecreatefromstring($binary);
        if (! $image instanceof \GdImage) {
            return $binary;
        }

        try {
            $width = imagesx($image);
            $size  = max(19, min(42, (int) round($width / 24)));
            $label = 'TAVSSCORE';
            $box   = imagettfbbox($size, 0, $font, $label);
            $textWidth = $box[2] - $box[0];
            $x = max(18, $width - $textWidth - 28);
            $y = $size + 22;
            $shadow = imagecolorallocatealpha($image, 0, 0, 0, 48);
            $white  = imagecolorallocatealpha($image, 255, 255, 255, 42);
            imagettftext($image, $size, 0, $x + 2, $y + 2, $shadow, $font, $label);
            imagettftext($image, $size, 0, $x, $y, $white, $font, $label);

            ob_start();
            if (str_starts_with($binary, "\x89PNG\r\n\x1A\n")) {
                imagepng($image, null, 7);
            } else {
                imagejpeg($image, null, 90);
            }
            return (string) ob_get_clean();
        } finally {
            imagedestroy($image);
        }
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
            if (is_file($font) && is_readable($font)) return $font;
        }
        return null;
    }
}
