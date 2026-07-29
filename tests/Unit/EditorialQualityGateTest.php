<?php

namespace Tests\Unit;

use App\Services\Blog\EditorialQualityGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialQualityGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_thin_generic_content(): void
    {
        $gate = app(EditorialQualityGate::class);

        $issues = $gate->issues('Short football update', '<p>It is worth noting that this is brief.</p>');

        $this->assertNotEmpty($issues);
        $this->assertContains('Article must contain at least 750 useful words.', $issues);
    }

    public function test_it_sanitises_untrusted_html(): void
    {
        $gate = app(EditorialQualityGate::class);

        $content = $gate->sanitise('<p>Useful football analysis.</p><script>alert(1)</script><a href="https://invalid.test">Fake source</a><img src="fake.jpg">');

        $this->assertSame('<p>Useful football analysis.</p>Fake source', $content);
    }
}
