<?php

namespace App\Services\Blog;

use App\Services\OpenAiBlogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Generates a related, self-hosted hero image for a blog post. OpenAI produces
 * the editorial visual; a local overlay always adds the Tavs Score watermark.
 * A blog image must be a real generated image. Fail clearly when OpenAI image
 * generation is unavailable instead of silently publishing an SVG placeholder.
 */
class HeroImageService
{
    public function generate(string $title, string $category, string $slug): string
    {
        $openAi = app(OpenAiBlogService::class);

        if (! $openAi->configured()) {
            throw new \RuntimeException('OpenAI image generation is not configured. Add a valid OPENAI_API_KEY before generating this image.');
        }

        try {
            $source = $openAi->generateImage($this->imagePrompt($title, $category));
            return $this->saveWatermarkedImage($source, $slug);
        } catch (\Throwable $e) {
            Log::warning('OpenAI blog hero generation failed.', ['title' => $title, 'error' => $e->getMessage()]);
            throw new \RuntimeException('OpenAI could not generate a real blog image. ' . $e->getMessage(), previous: $e);
        }
    }

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

    private function imagePrompt(string $title, string $category): string
    {
        $visual = $this->visualDirection($title, $category);

        return "Create a premium editorial football-news hero photograph for this Tavs Score article: '{$title}'. "
            . "Visual direction: {$visual} Use believable faces, authentic-looking modern football kits and cinematic stadium lighting. Compose the scene with clear room near the bottom-left for a watermark. "
            . 'Do not include words, letters, logos, watermarks, scoreboards, or brand marks in the generated image.';
    }

    private function visualDirection(string $title, string $category): string
    {
        if (preg_match('/^(.+?)\s+(?:vs\.?|v\.?|versus)\s+(.+?)(?:\s*[:\-].*)?$/i', trim($title), $teams)) {
            return "A dramatic face-to-face match-preview composition: one recognisable {$teams[1]} player in that club's colours on the left and one recognisable {$teams[2]} player in that club's colours on the right, both looking determined, with a floodlit stadium behind them.";
        }

        if (str_contains(strtolower($category . ' ' . $title), 'transfer')
            || preg_match('/\b(signs?|signed|joins?|deal|move|departure|loan)\b/i', $title)) {
            return "A transfer-news portrait focused on the named footballer in the headline, in a realistic training-ground or stadium setting that suggests a major career move. Keep the player as the sole clear subject, with a subtle club-colour backdrop.";
        }

        return "An editorial photograph centred on the named player, club or football story in the headline. Use the most relevant recognisable football subject, genuine sporting emotion and a setting that directly supports the story rather than a generic football scene.";
    }

    private function saveWatermarkedImage(string $imageBytes, string $slug): string
    {
        $image = @imagecreatefromstring($imageBytes);
        if ($image === false) {
            throw new \RuntimeException('OpenAI returned an unsupported image format.');
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
