<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Services\Blog\HeroImageService;
use App\Services\GroqBlogService;
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

    public function store(Request $request, HeroImageService $images): RedirectResponse
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
            $data['image_path']     = $images->saveUploadedImage($request->file('image_file'));
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

    public function update(Request $request, BlogPost $blog, HeroImageService $images): RedirectResponse
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
            $data['image_path']     = $images->saveUploadedImage($request->file('image_file'));
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
        if (! app(GroqBlogService::class)->configured()) {
            return redirect()->route('admin.blog.index')
                ->with('error', 'Groq API key is not configured. Add GROQ_API_KEY to your .env file.');
        }

        $before = BlogPost::max('id');

        // Delegate to the robust command: prefers today's fixtures, broadens to
        // any covered league, then falls back to a news roundup — and feeds real
        // transfers/injuries/standings/scorers so it never dead-ends on off-days.
        try {
            \Illuminate\Support\Facades\Artisan::call('blog:auto-post', ['--force' => true]);
        } catch (\Throwable $e) {
            Log::error('Auto blog generation failed.', ['message' => $e->getMessage()]);
            return redirect()->route('admin.blog.index')->with('error', 'AI generation failed: '.$e->getMessage());
        }

        $post = BlogPost::query()->where('id', '>', (int) $before)->latest('id')->first();

        if (! $post) {
            return redirect()->route('admin.blog.index')
                ->with('error', 'Could not generate a post right now — no fixtures and no football news available yet. Try after stats have been fetched.');
        }

        return redirect()->route('admin.blog.edit', $post)
            ->with('success', 'AI blog post generated and published successfully!');
    }

    /** Replace the text only, leaving the existing image untouched. */
    public function regenerateArticle(BlogPost $blog, GroqBlogService $groq): RedirectResponse
    {
        if (! $groq->configured()) {
            return back()->with('error', 'Groq API key is not configured. Add GROQ_API_KEY to your .env file.');
        }

        try {
            $article = $groq->writeArticle(
                'You are a senior TavsScore football editor. Return only valid JSON with exactly "title" and "content". Use the supplied article as the only factual source. Improve clarity, originality, structure and SEO without inventing facts. Use only p, h2, h3, ul, li and strong HTML tags. Never use em dashes.',
                "Regenerate this football news article. Keep it at least 600 words when the source contains enough factual material. Preserve every factual claim unless the source is uncertain.\n\nCURRENT TITLE: {$blog->title}\n\nCURRENT ARTICLE HTML:\n{$blog->content}",
            );
            $content = preg_replace('/<img[^>]*>/i', '', $article['content']);
            $content = preg_replace('/<a\b[^>]*>(.*?)<\/a>/is', '$1', $content);
            $blog->update([
                'title' => $article['title'],
                'content' => $content,
                'excerpt' => $this->excerpt((string) $content),
                'is_ai_generated' => true,
            ]);
            return redirect()->route('admin.blog.edit', $blog)->with('success', 'Article regenerated. The existing image was kept.');
        } catch (\Throwable $e) {
            Log::error('Blog article regeneration failed', ['blog_id' => $blog->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Article regeneration failed. Your current article was not changed.');
        }
    }

    private function excerpt(string $html): string
    {
        $text = preg_replace('/\s+/', ' ', trim(strip_tags($html)));
        return Str::limit($text, 155, '…');
    }

    private function deleteImage(?string $imagePath): void
    {
        if ($imagePath && file_exists(public_path($imagePath))) {
            unlink(public_path($imagePath));
        }
    }
}
