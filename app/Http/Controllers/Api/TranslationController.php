<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Prediction;
use App\Services\GroqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    public function __construct(private readonly GroqService $groqService) {}

    /**
     * Lazy-translate a prediction's analysis into Pidgin or Swahili.
     * Caches the result on the Prediction row so we only pay Groq once per pick per language.
     */
    public function translate(Request $request, int $predictionId): JsonResponse
    {
        $request->validate([
            'lang' => 'required|in:pidgin,swahili',
        ]);

        $lang = $request->string('lang')->toString();
        $col  = $lang === 'pidgin' ? 'analysis_pidgin' : 'analysis_swahili';

        $pred = Prediction::query()->findOrFail($predictionId);

        // Cache hit
        if (! blank($pred->{$col})) {
            return response()->json([
                'lang' => $lang,
                'text' => $pred->{$col},
                'cached' => true,
            ]);
        }

        if (blank($pred->analysis) || $pred->analysis === GroqService::FALLBACK_ANALYSIS) {
            return response()->json(['error' => 'No analysis to translate'], 422);
        }

        $translated = $this->groqService->translateAnalysis($pred->analysis, $lang);

        if (blank($translated)) {
            return response()->json(['error' => 'Translation failed'], 502);
        }

        $pred->update([$col => $translated]);

        return response()->json([
            'lang' => $lang,
            'text' => $translated,
            'cached' => false,
        ]);
    }

    /**
     * Lazy-translate a blog post body into Pidgin or Swahili (cached on the row).
     */
    public function translateBlog(Request $request, int $postId): JsonResponse
    {
        $request->validate(['lang' => 'required|in:pidgin,swahili']);

        $lang = $request->string('lang')->toString();
        $col  = $lang === 'pidgin' ? 'content_pidgin' : 'content_swahili';

        $post = BlogPost::query()->findOrFail($postId);

        if (! blank($post->{$col})) {
            return response()->json(['lang' => $lang, 'html' => $post->{$col}, 'cached' => true]);
        }

        $translated = $this->groqService->translateLongform($post->content, $lang);

        if (blank($translated)) {
            return response()->json(['error' => 'Translation failed'], 502);
        }

        $post->update([$col => $translated]);

        return response()->json(['lang' => $lang, 'html' => $translated, 'cached' => false]);
    }
}
