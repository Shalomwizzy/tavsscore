<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BlogEditorialAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_flags_an_article_without_changing_it(): void
    {
        $post = BlogPost::create([
            'title' => 'Short football update',
            'slug' => 'short-football-update',
            'content' => '<p>It is worth noting that this is too short.</p>',
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        $exitCode = Artisan::call('blog:audit-editorial');

        $this->assertSame(0, $exitCode);
        $this->assertSame('<p>It is worth noting that this is too short.</p>', $post->fresh()->content);
    }
}
