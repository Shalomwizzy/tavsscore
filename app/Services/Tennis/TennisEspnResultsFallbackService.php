<?php

namespace App\Services\Tennis;

use App\Models\TennisMatch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Keyless ESPN back-up for finished ATP/WTA matches when the live provider
 * no longer retains a completed fixture in its plan. A result is accepted
 * only when both players and ESPN's final/winner flag match exactly.
 */
class TennisEspnResultsFallbackService
{
    /** @return array{checked:int, settled:int, rows:int} */
    public function settlePending(int $days = 14): array
    {
        $tz = 'Africa/Lagos';
        $matches = TennisMatch::query()
            ->with('prediction')
            ->where('source', 'live_tennis')
            ->whereIn('status', ['scheduled', 'live'])
            ->whereDate('match_date', '<=', now($tz)->toDateString())
            ->whereDate('match_date', '>=', now($tz)->subDays(max(1, $days))->toDateString())
            ->whereHas('prediction', fn ($query) => $query->whereNull('was_correct'))
            ->get();

        if ($matches->isEmpty()) {
            return ['checked' => 0, 'settled' => 0, 'rows' => 0];
        }

        // ESPN's date is UTC in some tournaments. Query the stored local date
        // and one day on either side, then deduplicate events by their ID.
        $dates = $matches->pluck('match_date')->filter()->flatMap(fn ($date) => [
            $date->copy()->subDay()->format('Ymd'),
            $date->format('Ymd'),
            $date->copy()->addDay()->format('Ymd'),
        ])->unique()->values();

        $events = [];
        foreach ($dates as $date) {
            try {
                $response = Http::acceptJson()->timeout(15)->get(
                    rtrim((string) config('services.espn.tennis_url'), '/').'/tennis/all/scoreboard',
                    ['dates' => $date, 'limit' => 1000],
                );
                if ($response->failed()) {
                    Log::warning('Tennis ESPN fallback returned HTTP '.$response->status());
                    continue;
                }
                foreach ((array) $response->json('events', []) as $event) {
                    $events[(string) ($event['id'] ?? md5(json_encode($event)))] = $event;
                }
            } catch (\Throwable $exception) {
                Log::warning('Tennis ESPN fallback request failed: '.$exception->getMessage());
            }
        }

        $settled = 0;
        foreach ($matches as $match) {
            foreach ($events as $event) {
                $result = $this->resultFor($match, $event);
                if ($result === null) {
                    continue;
                }

                $match->update([
                    'status' => 'completed',
                    'winner' => $result['winner'],
                    'score' => $result['score'],
                    'stats' => array_merge($match->stats ?? [], [
                        'result_source' => 'espn_tennis',
                        'espn_event_id' => (string) ($event['id'] ?? ''),
                    ]),
                ]);
                $match->prediction?->update(['was_correct' => $match->prediction->predicted_winner === $result['winner']]);
                $settled++;
                break;
            }
        }

        return ['checked' => $matches->count(), 'settled' => $settled, 'rows' => count($events)];
    }

    /** @return array{winner:string, score:?string}|null */
    private function resultFor(TennisMatch $match, array $event): ?array
    {
        if (! data_get($event, 'status.type.completed', false) && strtoupper((string) data_get($event, 'status.type.state')) !== 'FINAL') {
            return null;
        }

        $competitors = (array) data_get($event, 'competitions.0.competitors', []);
        $first = $this->findPlayer($competitors, $match->player_one);
        $second = $this->findPlayer($competitors, $match->player_two);
        if ($first === null || $second === null) {
            return null;
        }

        $winner = null;
        if (filter_var($first['winner'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $winner = $match->player_one;
        } elseif (filter_var($second['winner'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $winner = $match->player_two;
        }
        if ($winner === null) {
            return null;
        }

        return ['winner' => $winner, 'score' => $this->score($first, $second)];
    }

    private function findPlayer(array $competitors, string $player): ?array
    {
        $expected = TennisNameNormalizer::canonical($player);
        foreach ($competitors as $competitor) {
            $name = data_get($competitor, 'athlete.displayName')
                ?? data_get($competitor, 'team.displayName')
                ?? data_get($competitor, 'displayName');
            if (TennisNameNormalizer::canonical((string) $name) === $expected) {
                return $competitor;
            }
        }

        return null;
    }

    private function score(array $first, array $second): ?string
    {
        $one = (array) ($first['linescores'] ?? []);
        $two = (array) ($second['linescores'] ?? []);
        if ($one === [] || $two === []) {
            return null;
        }

        $sets = [];
        foreach ($one as $index => $set) {
            $a = data_get($set, 'displayValue', data_get($set, 'value'));
            $b = data_get($two[$index] ?? [], 'displayValue', data_get($two[$index] ?? [], 'value'));
            if ($a !== null && $b !== null) {
                $sets[] = $a.'-'.$b;
            }
        }

        return $sets === [] ? null : implode(', ', $sets);
    }
}
