<?php

namespace App\Services\Tennis;

use App\Models\TennisMatch;
use Illuminate\Support\Facades\Http;

/** OpenAI cross-check for tennis, bounded by the statistical model rather than replacing it. */
class OpenAiTennisOpinionService
{
    public function opinion(TennisMatch $match, array $features, float $oneProbability): ?array
    {
        $key = config('services.openai.key');
        if (blank($key) || $key === 'your_openai_api_key_here') return null;
        $prompt = "You are a cautious tennis analyst. Pick the more likely winner using only these supplied signals. Do not invent injuries, news, or statistics.\n"
            . "MATCH: {$match->player_one} vs {$match->player_two}. Tour {$match->tour}, surface {$match->surface}.\n"
            . 'MODEL FEATURES: ' . json_encode($features) . "\n"
            . "Statistical-model probability for {$match->player_one}: " . round($oneProbability * 100, 1) . "%\n"
            . "Return only JSON: {\"winner\":\"exact player name\",\"confidence\":0,\"reason\":\"brief evidence-based reason\"}. Winner must be exactly {$match->player_one} or {$match->player_two}.";
        try {
            $response = Http::withToken($key)->acceptJson()->asJson()->timeout(30)
                ->post(rtrim(config('services.openai.url'), '/') . '/chat/completions', [
                    'model' => config('services.openai.text_model'), 'temperature' => 0.1, 'max_tokens' => 120,
                    'response_format' => ['type' => 'json_object'], 'messages' => [['role' => 'user', 'content' => $prompt]],
                ]);
            if ($response->failed()) return null;
            $data = json_decode(trim((string) data_get($response->json(), 'choices.0.message.content')), true);
            if (! is_array($data) || ! in_array($data['winner'] ?? null, [$match->player_one, $match->player_two], true)) return null;
            return ['winner' => $data['winner'], 'confidence' => max(0, min(100, (int) ($data['confidence'] ?? 0))), 'reason' => trim((string) ($data['reason'] ?? ''))];
        } catch (\Throwable) {
            return null;
        }
    }
}
