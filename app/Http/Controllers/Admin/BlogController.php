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
        if (blank(config('services.groq.key')) || config('services.groq.key') === 'your_api_key_here') {
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
