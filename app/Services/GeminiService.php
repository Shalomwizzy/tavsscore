<?php

namespace App\Services;

use App\Models\FootballMatch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Optional second AI for cross-validation. Only fires if GEMINI_API_KEY is set.
 *
 * We use this as a "second opinion" — if Gemini and Groq agree on the headline
 * tip, we mark it as cross-validated. Disagreement lowers our confidence.
 *
 * Free tier: 15 RPM, 1500 RPD on gemini-2.0-flash. Plenty for daily-pick
 * cross-validation (we only need 3 calls/day for that).
 */
class GeminiService
{
    public function isConfigured(): bool
    {
        return ! blank(config('services.gemini.key'));
    }

    /**
     * Get Gemini's predicted headline market for a match. Returns the market
     * label string or null on any failure.
     */
    public function headlineTip(FootballMatch $match): ?string
    {
        if (! $this->isConfigured()) return null;

        $prompt = <<<PROMPT
You are a senior football analyst. Given the fixture below, name the SINGLE bet market you'd most confidently recommend.

Match: {$match->home_team} vs {$match->away_team}
League: {$match->league} ({$match->league_country})

Choose ONE from this exact list (return ONLY the chosen string, nothing else):
- Home Win
- Draw
- Away Win
- Home or Draw (1X)
- Draw or Away (X2)
- Home or Away (12)
- Over 1.5 Goals
- Over 2.5 Goals
- Over 3.5 Goals
- Under 2.5 Goals
- Both Teams Score (GG)
- No Both Teams Score (NG)
- Home -1 Handicap
- Away +1 Handicap

Output ONLY the market label, no quotes, no explanation.
PROMPT;

        try {
            $response = Http::timeout(20)
                ->withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/' . config('services.gemini.model', 'gemini-2.0-flash') . ':generateContent', [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 30,
                    ],
                ]);
        } catch (ConnectionException | Throwable $e) {
            Log::info('Gemini request failed', ['match' => $match->id, 'error' => $e->getMessage()]);
            return null;
        }

        if ($response->failed()) return null;

        $text = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text'));
        $text = trim($text, "\"' \n\r\t");

        return $text === '' ? null : $text;
    }
}
