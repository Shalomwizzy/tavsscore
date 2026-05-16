<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WinnersSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure upload directory exists for tests
        if (! is_dir(public_path('images/winners'))) {
            mkdir(public_path('images/winners'), 0755, true);
        }
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'username'   => 'TestUser',
            'email'      => 'test@example.com',
            'screenshots' => [
                UploadedFile::fake()->image('win.jpg', 400, 300),
            ],
        ], $overrides);
    }

    // ── Happy path ───────────────────────────────────────────────────

    public function test_valid_jpg_submission_succeeds(): void
    {
        $this->post(route('winners.submit'), $this->validPayload())
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_valid_png_submission_succeeds(): void
    {
        $this->post(route('winners.submit'), $this->validPayload([
            'screenshots' => [UploadedFile::fake()->image('win.png', 400, 300)],
        ]))->assertRedirect()->assertSessionHas('success');
    }

    public function test_valid_webp_submission_succeeds(): void
    {
        $this->post(route('winners.submit'), $this->validPayload([
            'screenshots' => [UploadedFile::fake()->image('win.webp', 400, 300)],
        ]))->assertRedirect()->assertSessionHas('success');
    }

    public function test_multiple_valid_screenshots_succeed(): void
    {
        $this->post(route('winners.submit'), $this->validPayload([
            'screenshots' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
                UploadedFile::fake()->image('c.png'),
            ],
        ]))->assertRedirect()->assertSessionHas('success');
    }

    // ── MIME type rejection ──────────────────────────────────────────

    public function test_svg_upload_is_rejected(): void
    {
        $this->post(route('winners.submit'), $this->validPayload([
            'screenshots' => [
                UploadedFile::fake()->create('exploit.svg', 10, 'image/svg+xml'),
            ],
        ]))->assertSessionHasErrors('screenshots.0');
    }

    public function test_pdf_upload_is_rejected(): void
    {
        $this->post(route('winners.submit'), $this->validPayload([
            'screenshots' => [
                UploadedFile::fake()->create('file.pdf', 100, 'application/pdf'),
            ],
        ]))->assertSessionHasErrors('screenshots.0');
    }

    public function test_php_file_upload_is_rejected(): void
    {
        $this->post(route('winners.submit'), $this->validPayload([
            'screenshots' => [
                UploadedFile::fake()->create('shell.php', 10, 'text/plain'),
            ],
        ]))->assertSessionHasErrors('screenshots.0');
    }

    // ── Size and count limits ────────────────────────────────────────

    public function test_oversized_file_is_rejected(): void
    {
        $this->post(route('winners.submit'), $this->validPayload([
            'screenshots' => [
                UploadedFile::fake()->image('big.jpg')->size(6000), // 6 MB > 5 MB limit
            ],
        ]))->assertSessionHasErrors('screenshots.0');
    }

    public function test_too_many_files_is_rejected(): void
    {
        $files = [];
        for ($i = 0; $i < 6; $i++) {
            $files[] = UploadedFile::fake()->image("shot{$i}.jpg");
        }

        $this->post(route('winners.submit'), $this->validPayload([
            'screenshots' => $files,
        ]))->assertSessionHasErrors('screenshots');
    }

    public function test_submission_requires_username(): void
    {
        $payload = $this->validPayload();
        unset($payload['username']);

        $this->post(route('winners.submit'), $payload)
            ->assertSessionHasErrors('username');
    }

    public function test_submission_requires_valid_email(): void
    {
        $this->post(route('winners.submit'), $this->validPayload([
            'email' => 'not-an-email',
        ]))->assertSessionHasErrors('email');
    }

    public function test_submission_requires_at_least_one_screenshot(): void
    {
        $this->post(route('winners.submit'), $this->validPayload([
            'screenshots' => [],
        ]))->assertSessionHasErrors('screenshots');
    }
}
