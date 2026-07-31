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
                        'player_one' => TennisNameNormalizer::canonical(data_get($item, 'players.p1.name')),
                        'player_two' => TennisNameNormalizer::canonical(data_get($item, 'players.p2.name')),
                        'player_one_country' => $this->countryCode(data_get($item, 'players.p1.country')),
                        'player_two_country' => $this->countryCode(data_get($item, 'players.p2.country')),
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
        // Do not limit this to the last few hours. A provider outage, a quota
        // interruption, or a delayed scheduler run used to make older Tennis
        // fixtures invisible forever. Restrict to actual unsettled predictions
        // with a saved Live Tennis ID, then retry every past tracked match.
        $matches = TennisMatch::query()
            ->where('source', 'live_tennis')
            ->whereIn('status', ['scheduled', 'live'])
            ->whereDate('match_date', '<=', now('Africa/Lagos')->toDateString())
            ->whereHas('prediction', fn ($query) => $query->whereNull('was_correct'))
            ->orderByRaw('scheduled_at is null')
            ->orderBy('scheduled_at')
            ->get();
        foreach ($matches as $match) {
            $checked++;
            try { $item = $this->get('/matches/' . $match->source_key); } catch (\Throwable) {
                if ($this->settleFromImportedHistory($match)) {
                    $settled++;
                }
                continue;
            }
            $status = strtolower((string) ($item['status'] ?? ''));
            if ($status === 'completed' && in_array((int) ($item['winner'] ?? 0), [1, 2], true)) {
                $this->settleMatch(
                    $match,
                    (int) $item['winner'] === 1 ? $match->player_one : $match->player_two,
                    $this->score($item['score'] ?? null),
                    ['last_live_score' => $item['score'] ?? null, 'result_source' => 'live_tennis'],
                );
                $settled++;
            } elseif ($status === 'live') {
                $match->update(['status' => 'live', 'stats' => array_merge($match->stats ?? [], ['last_live_score' => $item['score'] ?? null])]);
            } elseif ($match->match_date?->lt(now('Africa/Lagos')->startOfDay()) && $this->settleFromImportedHistory($match)) {
                // Some Live Tennis plans retain live/current match detail but
                // not older completed resources. Match our separately imported
                // verified result by date and the exact two players instead.
                $settled++;
            }
        }
        return compact('checked', 'settled');
    }

    private function settleFromImportedHistory(TennisMatch $match): bool
    {
        $historical = TennisMatch::query()
            ->where('source', 'tennisdata')
            ->where('status', 'completed')
            ->whereDate('match_date', $match->match_date?->toDateString())
            ->where(fn ($query) => $query
                ->where(fn ($same) => $same->where('player_one', $match->player_one)->where('player_two', $match->player_two))
                ->orWhere(fn ($reversed) => $reversed->where('player_one', $match->player_two)->where('player_two', $match->player_one)))
            ->latest('id')
            ->first();

        if (! $historical?->winner || ! in_array($historical->winner, [$match->player_one, $match->player_two], true)) {
            return false;
        }

        $this->settleMatch($match, $historical->winner, $historical->score, [
            'result_source' => 'tennisdata',
            'result_match_id' => $historical->id,
        ]);

        return true;
    }

    /** @param array<string, mixed> $extraStats */
    private function settleMatch(TennisMatch $match, string $winner, ?string $score, array $extraStats): void
    {
        $match->update([
            'status' => 'completed',
            'winner' => $winner,
            'score' => $score,
            'stats' => array_merge($match->stats ?? [], $extraStats),
        ]);
        if ($match->prediction) {
            $match->prediction->update(['was_correct' => $match->prediction->predicted_winner === $winner]);
        }
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

    /** Normalise an ISO country code (e.g. "gre") to upper-case, or null. */
    private function countryCode(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));
        return preg_match('/^[A-Z]{2,3}$/', $code) ? $code : null;
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
