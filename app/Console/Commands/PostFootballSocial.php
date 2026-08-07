<?php

namespace App\Console\Commands;

use App\Services\FootballSocialComposer;
use App\Services\XService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Composes one data-driven football post and publishes it to X, to grow the
 * @tavsscore account between booking-code posts. No-ops cleanly when X is not
 * configured or no content is available. Avoids repeating the previous post.
 */
class PostFootballSocial extends Command
{
    protected $signature = 'x:post-football';

    protected $description = 'Compose and post a data-driven football growth post to X.';

    public function handle(FootballSocialComposer $composer, XService $x): int
    {
        if (! $x->isConfigured()) {
            $this->warn('X is not configured — skipping.');

            return self::SUCCESS;
        }

        $last = Cache::get('x_last_football_post');
        $text = null;
        for ($i = 0; $i < 4; $i++) {
            $candidate = $composer->compose();
            if (! $candidate) {
                break;
            }
            $text = $candidate;
            if ($candidate !== $last) {
                break; // got a fresh one — don't repeat the previous post
            }
        }

        if (! $text) {
            $this->warn('No football content available to post.');

            return self::SUCCESS;
        }

        $x->postText($text);
        Cache::put('x_last_football_post', $text, now()->addDays(3));
        $this->info('Posted to X: '.mb_substr(str_replace("\n", ' ', $text), 0, 70).'…');

        return self::SUCCESS;
    }
}
