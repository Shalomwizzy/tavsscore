@php
    $moreTitle = $moreTitle ?? 'Explore TavsScore';
    $moreDescription = $moreDescription ?? 'Follow the data, results and community behind every TavsScore prediction.';
    $moreKicker = $moreKicker ?? 'TavsScore insight';
@endphp

<section class="more-hero">
    <div class="more-hero-copy">
        <span class="more-hero-kicker">{{ $moreKicker }}</span>
        <h1 class="more-hero-title">{{ $moreTitle }}</h1>
        <p>{{ $moreDescription }}</p>
    </div>
    <nav class="more-hero-tabs" aria-label="Explore TavsScore">
        <a href="{{ route('stats.index') }}" class="{{ request()->routeIs('stats.index') ? 'active' : '' }}">📊 Stats</a>
        <a href="{{ route('standings.index') }}" class="{{ request()->routeIs('standings.index','top-scorers.index') ? 'active' : '' }}">🏆 Tables</a>
        <a href="{{ route('track-record.index') }}" class="{{ request()->routeIs('track-record.index') ? 'active' : '' }}">📈 Track record</a>
        <a href="{{ route('results.index','daily-football-predictions.*') }}" class="{{ request()->routeIs('results.index','daily-football-predictions.*') ? 'active' : '' }}">📜 Results</a>
        <a href="{{ route('winners.index') }}" class="{{ request()->routeIs('winners.*','hall-of-fame.*') ? 'active' : '' }}">🏆 Winners</a>
        <a href="{{ route('about') }}" class="{{ request()->routeIs('about') ? 'active' : '' }}">ℹ️ About</a>
    </nav>
</section>
