<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
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

        $categories = BlogPost::published()
            ->select('category')
            ->distinct()
            ->pluck('category');

        return view('blog.index', compact('posts', 'categories', 'category'));
    }

    public function show(string $slug): View
    {
        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();

        $related = BlogPost::published()
            ->where('id', '!=', $post->id)
            ->where('category', $post->category)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('blog.show', compact('post', 'related'));
    }
}
