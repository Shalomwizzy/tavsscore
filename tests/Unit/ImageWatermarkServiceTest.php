<?php

namespace Tests\Unit;

use App\Services\ImageWatermarkService;
use Tests\TestCase;

class ImageWatermarkServiceTest extends TestCase
{
    public function test_it_returns_a_valid_branded_jpeg(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is required for image watermarking.');
        }

        $image = imagecreatetruecolor(480, 270);
        imagefilledrectangle($image, 0, 0, 480, 270, imagecolorallocate($image, 20, 32, 49));
        ob_start(); imagejpeg($image, null, 90); $source = (string) ob_get_clean(); imagedestroy($image);

        $branded = app(ImageWatermarkService::class)->stamp($source);

        $this->assertStringStartsWith("\xFF\xD8\xFF", $branded);
        $this->assertNotSame($source, $branded);
    }
}
