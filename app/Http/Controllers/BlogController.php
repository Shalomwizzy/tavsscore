<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        $category = request('category');

        $query = BlogPost::published()->orderByDesc('published_at');

        if ($category) {
            $query->where('category', $category);
        }

        $posts = $query->paginate(12)->withQueryString();

        $canonicalParameters = array_filter([
            'category' => $category,
            'page' => $posts->currentPage() > 1 ? $posts->currentPage() : null,
        ], fn ($value) => $value !== null && $value !== '');
        $canonical = url('/football-news') . ($canonicalParameters ? '?' . http_build_query($canonicalParameters) : '');

        $categories = BlogPost::published()
            ->select('category')
            ->distinct()
            ->pluck('category');

        return view('blog.index', compact('posts', 'categories', 'category', 'canonical'));
    }

    public function show(int $year, int $month, int $day, string $slug): View|RedirectResponse
    {
        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();

        $requestedPath = sprintf('/football-news/%04d/%02d/%02d/%s', $year, $month, $day, $slug);
        if ($post->public_path !== $requestedPath) {
            return redirect()->to($post->public_url, 301);
        }

        $related = BlogPost::published()
            ->where('id', '!=', $post->id)
            ->where('category', $post->category)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('blog.show', compact('post', 'related'));
    }

    public function legacyIndex(Request $request): RedirectResponse
    {
        $query = $request->getQueryString();

        return redirect()->to(url('/football-news') . ($query ? '?' . $query : ''), 301);
    }

    public function legacyShow(string $slug): RedirectResponse
    {
        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();

        return redirect()->to($post->public_url, 301);
    }
}
