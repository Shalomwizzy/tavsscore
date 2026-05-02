<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">

    @foreach($staticPages as $page)
    <url>
        <loc>{{ $page['url'] }}</loc>
        <changefreq>{{ $page['freq'] }}</changefreq>
        <priority>{{ $page['priority'] }}</priority>
        <lastmod>{{ now()->toAtomString() }}</lastmod>
    </url>
    @endforeach

    @foreach($matchPages as $match)
    <url>
        <loc>{{ route('predictions.show', $match->slug) }}</loc>
        <changefreq>daily</changefreq>
        <priority>0.6</priority>
        <lastmod>{{ $match->updated_at->toAtomString() }}</lastmod>
    </url>
    @endforeach

    @foreach($posts as $post)
    <url>
        <loc>{{ route('blog.show', $post->slug) }}</loc>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
        <lastmod>{{ $post->updated_at->toAtomString() }}</lastmod>
        @if($post->published_at && $post->published_at->greaterThan(now()->subDays(2)))
        <news:news>
            <news:publication>
                <news:name>TavsScore</news:name>
                <news:language>en</news:language>
            </news:publication>
            <news:publication_date>{{ $post->published_at->toAtomString() }}</news:publication_date>
            <news:title>{{ htmlspecialchars($post->title) }}</news:title>
        </news:news>
        @endif
    </url>
    @endforeach

</urlset>
