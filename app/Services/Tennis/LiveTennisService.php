<?php

namespace App\Services\Tennis;

use App\Models\TennisMatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Imports live fixtures and settles only matches TavsScore has already tracked. */
class LiveTennisService
{
    public function syncFixtures(): int
    {
        $count = 0;
        foreach (['atp' => 'ATP', 'wta' => 'WTA'] as $apiTour => $tour) {
            $payload = $this->get('/matches', ['status' => 'upcoming', 'tour' => $apiTour, 'limit' => 200]);
            foreach ((array) ($payload['data'] ?? []) as $item) {
                if (($item['is_doubles'] ?? false) || blank(data_get($item, 'players.p1.name')) || blank(data_get($item, 'players.p2.name'))) continue;
                $scheduled = $this->date(data_get($item, 'scheduled_time'));
                TennisMatch::updateOrCreate(
                    ['source' => 'live_tennis', 'source_key' => (string) $item['id']],
                    [
                        'tour' => $tour, 'tournament' => $item['tournament'] ?? null,
                        'surface' => isset($item['surface']) ? ucfirst((string) $item['surface']) : null,
                        'match_date' => $scheduled?->toDateString(), 'scheduled_at' => $scheduled,
                        'round' => $item['round'] ?? null, 'best_of' => $this->bestOf($item['format'] ?? null),
                        'player_one' => data_get($item, 'players.p1.name'), 'player_two' => data_get($item, 'players.p2.name'),
                        'status' => 'scheduled', 'stats' => ['live_tennis_id' => $item['id']],
                    ],
                );
                $count++;
            }
        }
        return $count;
    }

    /** @return array{checked:int, settled:int} */
    public function settleTracked(): array
    {
        $checked = $settled = 0;
        $matches = TennisMatch::query()->where('source', 'live_tennis')
            ->whereIn('status', ['scheduled', 'live'])
            ->whereNotNull('scheduled_at')->whereBetween('scheduled_at', [now()->subHours(18), now()->addMinutes(30)])
            ->orderBy('scheduled_at')->get();
        foreach ($matches as $match) {
            $checked++;
            try { $item = $this->get('/matches/' . $match->source_key); } catch (\Throwable) { continue; }
            $status = strtolower((string) ($item['status'] ?? ''));
            if ($status === 'completed' && in_array((int) ($item['winner'] ?? 0), [1, 2], true)) {
                $winner = (int) $item['winner'] === 1 ? $match->player_one : $match->player_two;
                $match->update(['status' => 'completed', 'winner' => $winner, 'score' => $this->score($item['score'] ?? null), 'stats' => array_merge($match->stats ?? [], ['last_live_score' => $item['score'] ?? null])]);
                if ($match->prediction) $match->prediction->update(['was_correct' => $match->prediction->predicted_winner === $winner]);
                $settled++;
            } elseif ($status === 'live') {
                $match->update(['status' => 'live', 'stats' => array_merge($match->stats ?? [], ['last_live_score' => $item['score'] ?? null])]);
            }
        }
        return compact('checked', 'settled');
    }

    private function get(string $path, array $query = []): array
    {
        if (blank(config('services.tennis_live.key'))) throw new RuntimeException('TENNIS_LIVE_API_KEY is not configured.');
        $response = Http::withToken(config('services.tennis_live.key'))->acceptJson()->timeout(25)
            ->get(rtrim(config('services.tennis_live.url'), '/') . $path, $query);
        if ($response->failed()) throw new RuntimeException('Live Tennis API error: ' . $response->status());
        return $response->json();
    }

    private function date(?string $value): ?CarbonImmutable
    {
        try { return $value ? CarbonImmutable::parse($value) : null; } catch (\Throwable) { return null; }
    }

    private function bestOf(?string $format): ?int
    {
        return preg_match('/BO(\d)/i', (string) $format, $m) ? (int) $m[1] : null;
    }

    private function score(mixed $score): ?string
    {
        if (! is_array($score) || ! isset($score['games'][0], $score['games'][1])) return null;
        $one = (array) $score['games'][0]; $two = (array) $score['games'][1];
        $sets = [];
        foreach ($one as $i => $games) $sets[] = $games . '-' . ($two[$i] ?? '?');
        return implode(', ', $sets);
    }
}
