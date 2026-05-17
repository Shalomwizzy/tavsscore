<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Prediction;
use App\Services\GeminiService;
use App\Services\GroqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    public function __construct(
        private readonly GeminiService $geminiService,
        private readonly GroqService   $groqService,
    ) {}

    /**
     * Lazy-translate a prediction's analysis into Pidgin, Swahili, or French.
     * Tries Gemini first, falls back to Groq. Caches result on the Prediction row.
     */
    public function translate(Request $request, int $predictionId): JsonResponse
    {
        $request->validate([
            'lang' => 'required|in:pidgin,swahili,french',
        ]);

        $lang = $request->string('lang')->toString();
        $col  = match($lang) {
            'pidgin'  => 'analysis_pidgin',
            'swahili' => 'analysis_swahili',
            'french'  => 'analysis_french',
        };

        $pred = Prediction::query()->findOrFail($predictionId);

        if (! blank($pred->{$col})) {
            return response()->json([
                'lang'   => $lang,
                'text'   => $pred->{$col},
                'cached' => true,
            ]);
        }

        if (blank($pred->analysis)) {
            return response()->json(['error' => 'No analysis to translate'], 422);
        }

        $translated = $this->geminiService->translate($pred->analysis, $lang, false)
            ?? $this->groqService->translateAnalysis($pred->analysis, $lang);

        if (blank($translated)) {
            return response()->json(['error' => 'Translation failed'], 502);
        }

        $pred->update([$col => $translated]);

        return response()->json([
            'lang'   => $lang,
            'text'   => $translated,
            'cached' => false,
        ]);
    }

    /**
     * Lazy-translate a blog post body. Tries Gemini first, falls back to Groq.
     */
    public function translateBlog(Request $request, int $postId): JsonResponse
    {
        $request->validate(['lang' => 'required|in:pidgin,swahili,french']);

        $lang = $request->string('lang')->toString();
        $col  = match($lang) {
            'pidgin'  => 'content_pidgin',
            'swahili' => 'content_swahili',
            'french'  => 'content_french',
        };

        $post = BlogPost::query()->findOrFail($postId);

        if (! blank($post->{$col})) {
            return response()->json(['lang' => $lang, 'html' => $post->{$col}, 'cached' => true]);
        }

        $translated = $this->geminiService->translate($post->content, $lang, true)
            ?? $this->groqService->translateLongform($post->content, $lang);

        if (blank($translated)) {
            return response()->json(['error' => 'Translation failed'], 502);
        }

        $post->update([$col => $translated]);

        return response()->json(['lang' => $lang, 'html' => $translated, 'cached' => false]);
    }
}
