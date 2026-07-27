<?php

namespace App\Services\Blog;

use Illuminate\Support\Facades\File;

/**
 * Generates a branded, self-hosted hero image for a blog post — a 1200×630 SVG
 * saved under public/images/blog/. No external service, no auth, no fonts to
 * ship: it always renders, is always football-relevant (pitch motif + the post
 * title + league), and never 404s. Replaces the old random Picsum seeds.
 */
class HeroImageService
{
    // Accent + gradient per category so a Champions League post doesn't look
    // like a transfer post. [accent, gradTop, gradBottom].
    private const THEMES = [
        'Champions League' => ['#38bdf8', '#0b1a3a', '#020617'],
        'Premier League'   => ['#a78bfa', '#2a0f3a', '#0b0616'],
        'La Liga'          => ['#fbbf24', '#3a1a0b', '#160a02'],
        'Serie A'          => ['#34d399', '#062a1a', '#02160d'],
        'Bundesliga'       => ['#f87171', '#3a0b0b', '#160202'],
        'Ligue 1'          => ['#60a5fa', '#0b1f3a', '#020a16'],
        'Match Previews'   => ['#10b981', '#062a22', '#03130f'],
        'default'          => ['#10b981', '#0b1220', '#020617'],
    ];

    public function generate(string $title, string $category, string $slug): string
    {
        [$accent, $top, $bottom] = self::THEMES[$category] ?? self::THEMES['default'];

        $lines    = $this->wrap($title, 22, 4);
        $fontSize = count($lines) >= 4 ? 58 : (count($lines) === 3 ? 66 : 76);
        $blockH   = count($lines) * ($fontSize + 12);
        $startY   = 315 - ($blockH / 2) + $fontSize;

        $titleSvg = '';
        foreach ($lines as $i => $line) {
            $y = $startY + $i * ($fontSize + 12);
            $titleSvg .= sprintf(
                '<text x="80" y="%d" font-size="%d" font-weight="800" fill="#ffffff">%s</text>',
                $y, $fontSize, $this->esc($line)
            );
        }

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630" font-family="'Segoe UI',Roboto,Arial,Helvetica,sans-serif">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{$top}"/>
      <stop offset="1" stop-color="{$bottom}"/>
    </linearGradient>
  </defs>
  <rect width="1200" height="630" fill="url(#bg)"/>
  <g stroke="{$accent}" stroke-opacity="0.14" stroke-width="3" fill="none">
    <circle cx="1080" cy="150" r="150"/>
    <circle cx="1080" cy="150" r="55"/>
    <line x1="1080" y1="0" x2="1080" y2="300"/>
    <rect x="930" y="470" width="220" height="150"/>
    <rect x="1010" y="520" width="140" height="100"/>
  </g>
  <rect x="80" y="70" width="14" height="46" rx="3" fill="{$accent}"/>
  <text x="110" y="104" font-size="30" font-weight="700" fill="{$accent}" letter-spacing="1">{$this->esc(strtoupper($category))}</text>
  {$titleSvg}
  <text x="80" y="570" font-size="34" font-weight="800" fill="#ffffff">⚽ Tavs<tspan fill="{$accent}">Score</tspan></text>
  <text x="80" y="600" font-size="20" fill="#94a3b8">AI football predictions &amp; analysis</text>
</svg>
SVG;

        $dir = public_path('images/blog');
        File::ensureDirectoryExists($dir);
        $filename = 'hero-' . $slug . '.svg';
        File::put($dir . '/' . $filename, $svg);

        return '/images/blog/' . $filename;
    }

    /** Greedy word-wrap into at most $maxLines lines of ~$perLine chars. */
    private function wrap(string $text, int $perLine, int $maxLines): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lines = [];
        $cur   = '';

        foreach ($words as $w) {
            $try = $cur === '' ? $w : "$cur $w";
            if (mb_strlen($try) > $perLine && $cur !== '') {
                $lines[] = $cur;
                $cur = $w;
                if (count($lines) === $maxLines - 1) break;
            } else {
                $cur = $try;
            }
        }
        if ($cur !== '' && count($lines) < $maxLines) {
            $lines[] = $cur;
        }
        // If the title overflowed, ellipsize the last kept line.
        $used = implode(' ', $lines);
        if (mb_strlen($used) < mb_strlen(trim($text)) && ! empty($lines)) {
            $lines[count($lines) - 1] = rtrim($lines[count($lines) - 1]) . '…';
        }

        return $lines ?: [$text];
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
