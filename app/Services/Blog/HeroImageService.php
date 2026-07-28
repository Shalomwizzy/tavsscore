<?php

namespace App\Services\Blog;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Stores uploaded blog images and always adds the Tavs Score watermark.
 */
class HeroImageService
{
    /**
     * Store an editor-uploaded image as a watermarked PNG. This keeps the
     * Tavs Score mark consistent whether the original was made in ChatGPT
     * Plus, supplied by a writer, or generated through the API.
     */
    public function saveUploadedImage(UploadedFile $file): string
    {
        $bytes = file_get_contents($file->getRealPath());

        if ($bytes === false) {
            throw new \RuntimeException('The uploaded image could not be read.');
        }

        return ltrim($this->saveWatermarkedImage($bytes, 'upload-' . Str::uuid()), '/');
    }

    private function saveWatermarkedImage(string $imageBytes, string $slug): string
    {
        $image = @imagecreatefromstring($imageBytes);
        if ($image === false) {
            throw new \RuntimeException('The uploaded image uses an unsupported format.');
        }

        imagealphablending($image, true);
        $width = imagesx($image);
        $height = imagesy($image);
        $padding = max(20, (int) round($width * 0.025));
        $barHeight = max(56, (int) round($height * 0.115));
        $overlay = imagecolorallocatealpha($image, 2, 6, 23, 40);
        imagefilledrectangle($image, 0, $height - $barHeight, $width, $height, $overlay);

        $font = max(4, min(5, (int) floor($width / 300)));
        $brand = 'TAVS SCORE';
        $subline = 'FOOTBALL PREDICTIONS & ANALYSIS';
        $white = imagecolorallocate($image, 255, 255, 255);
        $accent = imagecolorallocate($image, 16, 185, 129);
        imagestring($image, $font, $padding, $height - $barHeight + $padding - 2, $brand, $white);
        imagestring($image, 2, $padding, $height - $padding - 12, $subline, $accent);

        $dir = public_path('images/blog');
        File::ensureDirectoryExists($dir);
        $filename = 'hero-' . $slug . '.png';
        $path = $dir . '/' . $filename;
        imagepng($image, $path, 8);
        imagedestroy($image);

        return '/images/blog/' . $filename;
    }

}
