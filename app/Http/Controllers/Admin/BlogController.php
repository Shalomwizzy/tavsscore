<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\FootballMatch;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        $posts = BlogPost::orderByDesc('created_at')->paginate(20);

        return view('admin.blog.index', compact('posts'));
    }

    public function create(): View
    {
        return view('admin.blog.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'slug'           => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'excerpt'        => ['nullable', 'string', 'max:500'],
            'content'        => ['required', 'string'],
            'image_file'     => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
            'featured_image' => ['nullable', 'url', 'max:500'],
            'category'       => ['required', 'string', 'max:100'],
            'author'         => ['required', 'string', 'max:100'],
            'is_published'   => ['boolean'],
        ]);

        $data['slug']         = !empty($data['slug']) ? $data['slug'] : BlogPost::generateSlug($data['title']);
        $data['is_published'] = $request->boolean('is_published');
        $data['published_at'] = $data['is_published'] ? now() : null;

        if ($request->hasFile('image_file')) {
            $data['image_path']     = $this->saveImage($request->file('image_file'));
            $data['featured_image'] = null;
        }
        unset($data['image_file']);

        BlogPost::create($data);

        return redirect()->route('admin.blog.index')
            ->with('success', 'Post created successfully.');
    }

    public function edit(BlogPost $blog): View
    {
        return view('admin.blog.edit', compact('blog'));
    }

    public function update(Request $request, BlogPost $blog): RedirectResponse
    {
        $data = $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'slug'           => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', 'unique:blog_posts,slug,' . $blog->id],
            'excerpt'        => ['nullable', 'string', 'max:500'],
            'content'        => ['required', 'string'],
            'image_file'     => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
            'featured_image' => ['nullable', 'url', 'max:500'],
            'category'       => ['required', 'string', 'max:100'],
            'author'         => ['required', 'string', 'max:100'],
            'is_published'   => ['boolean'],
        ]);

        $wasPublished         = $blog->is_published;
        $data['is_published'] = $request->boolean('is_published');

        if (! $wasPublished && $data['is_published']) {
            $data['published_at'] = now();
        }

        if ($request->hasFile('image_file')) {
            $this->deleteImage($blog->image_path);
            $data['image_path']     = $this->saveImage($request->file('image_file'));
            $data['featured_image'] = null;
        } else {
            unset($data['featured_image']);
        }
        unset($data['image_file']);

        $blog->update($data);

        return redirect()->route('admin.blog.index')
            ->with('success', 'Post updated successfully.');
    }

    public function destroy(BlogPost $blog): RedirectResponse
    {
        $this->deleteImage($blog->image_path);
        $blog->delete();

        return redirect()->route('admin.blog.index')
            ->with('success', 'Post deleted.');
    }

    public function autoGenerate(): RedirectResponse
    {
        $apiKey = config('services.groq.key');

        if (blank($apiKey) || $apiKey === 'your_api_key_here') {
            return redirect()->route('admin.blog.index')
                ->with('error', 'Groq API key is not configured. Add GROQ_API_KEY to your .env file.');
        }

        $today   = CarbonImmutable::now(config('app.timezone'));
        $matches = FootballMatch::whereDate('match_time', $today->toDateString())
            ->whereIn('league_id', [2, 3, 39, 61, 78, 135, 140, 848])
            ->orderBy('match_time')
            ->limit(6)
            ->get();

        if ($matches->isEmpty()) {
            return redirect()->route('admin.blog.index')
                ->with('error', 'No top-league matches found for today to generate a post about.');
        }

        $matchList = $matches->map(fn ($m) => "{$m->home_team} vs {$m->away_team} ({$m->league})")->implode(', ');
        $dateStr   = $today->format('l, F j Y');

        try {
            $response = \Illuminate\Support\Facades\Http::withToken($apiKey)
                ->acceptJson()->asJson()->timeout(30)
                ->post(config('services.groq.url'), [
                    'model'       => config('services.groq.model'),
                    'temperature' => 0.7,
                    'max_tokens'  => 900,
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => 'You are a professional football journalist writing for TavsScore, a football live scores and predictions website. Write engaging, SEO-friendly football news articles. Always return valid JSON with "title" and "content" fields. The content should be formatted as clean HTML paragraphs using <p> tags. Do not include <html>, <head>, or <body> tags.',
                        ],
                        [
                            'role'    => 'user',
                            'content' => "Write a football preview article for {$dateStr} covering these matches: {$matchList}.\n\nReturn JSON: {\"title\": \"...\", \"content\": \"<p>...</p><p>...</p>...\"}\n\nInclude: exciting intro paragraph, brief preview of each match, key storylines, what to watch. Keep it around 400-500 words. Make it SEO-friendly with natural keywords.",
                        ],
                    ],
                ]);

            $text = trim((string) data_get($response->json(), 'choices.0.message.content'));
            $text = preg_replace('/^```json\s*/i', '', $text);
            $text = preg_replace('/\s*```$/', '', $text);

            $json = json_decode($text, true);

            if (! $json || empty($json['title']) || empty($json['content'])) {
                throw new \RuntimeException('Groq returned invalid JSON structure.');
            }

            $post = BlogPost::create([
                'title'          => $json['title'],
                'slug'           => BlogPost::generateSlug($json['title']),
                'excerpt'        => strip_tags(substr($json['content'], 0, 200)).'…',
                'content'        => $json['content'],
                'category'       => 'Match Previews',
                'author'         => 'TavsScore AI',
                'is_published'   => true,
                'is_ai_generated'=> true,
                'published_at'   => now(),
            ]);

            return redirect()->route('admin.blog.edit', $post)
                ->with('success', 'AI blog post generated and published successfully!');
        } catch (\Throwable $e) {
            Log::error('Auto blog generation failed.', ['message' => $e->getMessage()]);

            return redirect()->route('admin.blog.index')
                ->with('error', 'AI generation failed: '.$e->getMessage());
        }
    }

    private function saveImage(\Illuminate\Http\UploadedFile $file): string
    {
        $filename  = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('images/blog'), $filename);
        return 'images/blog/' . $filename;
    }

    private function deleteImage(?string $imagePath): void
    {
        if ($imagePath && file_exists(public_path($imagePath))) {
            unlink(public_path($imagePath));
        }
    }
}
