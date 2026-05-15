<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AfricaController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CorrectScoreController;
use App\Http\Controllers\HallOfFameController;
use App\Http\Controllers\RolloverController;
use App\Http\Controllers\WinnersController;
use App\Http\Controllers\DailyPickController;
use App\Http\Controllers\LineupPicksController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LiveScoreController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PredictionPageController;
use App\Http\Controllers\ResultsController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

/* ── Public ── */
Route::get('/',            [HomeController::class, 'index'])->name('home.index');
Route::get('/live',        [LiveScoreController::class, 'index'])->name('live.index');
Route::get('/predictions',         [PredictionPageController::class, 'index'])->name('predictions.index');
Route::get('/predictions/{slug}',  [PredictionPageController::class, 'show'])->name('predictions.show')->where('slug', '[A-Za-z0-9-]+');
Route::get('/picks',        [DailyPickController::class, 'index'])->name('picks.index');
Route::get('/lineup-picks',  [LineupPicksController::class, 'index'])->name('lineup-picks.index');
Route::get('/correct-score', [CorrectScoreController::class, 'index'])->name('correct-score.index');
Route::get('/rollover',           [RolloverController::class, 'index'])->name('rollover.index');
Route::get('/rollover/{date}',    [RolloverController::class, 'show'])->name('rollover.show')->where('date', '\d{4}-\d{2}-\d{2}');
Route::get('/stats',       [StatsController::class, 'index'])->name('stats.index');
Route::get('/results',     [ResultsController::class, 'index'])->name('results.index');
Route::get('/africa',      [AfricaController::class, 'index'])->name('africa.index');

/* ── Winners ── */
Route::get('/winners',                  [WinnersController::class, 'index'])->name('winners.index');
Route::post('/winners/submit',          [WinnersController::class, 'submit'])->middleware('throttle:10,60')->name('winners.submit');
Route::get('/hall-of-fame',             [HallOfFameController::class, 'index'])->name('hall-of-fame.index');
Route::get('/winners/check-username',   [HallOfFameController::class, 'checkUsername'])->name('winners.check-username');

/* ── Blog ── */
Route::get('/blog',        [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

/* ── Static pages (required for AdSense approval) ── */
Route::get('/about',   [PageController::class, 'about'])->name('about');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms',   [PageController::class, 'terms'])->name('terms');
Route::get('/contact',  [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'contactSend'])->name('contact.send');

/* ── SEO ── */
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt',  [SeoController::class, 'robots'])->name('robots');

/* ── Newsletter ── */
Route::post('/newsletter/subscribe',          [NewsletterController::class, 'subscribe'])->middleware('throttle:5,1')->name('newsletter.subscribe');
Route::get('/newsletter/confirm/{token}',     [NewsletterController::class, 'confirm'])->name('newsletter.confirm');
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');

/* ── Admin ── */
Route::prefix('admin')->name('admin.')->group(function () {
    /* Guest-only */
    Route::middleware('guest')->group(function () {
        Route::get('/login',  [Admin\AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [Admin\AuthController::class, 'login'])->name('login.post');
    });

    /* Admin-protected */
    Route::middleware('admin')->group(function () {
        Route::get('/',            [Admin\DashboardController::class, 'index'])->name('dashboard');
        Route::post('/logout',     [Admin\AuthController::class, 'logout'])->name('logout');

        /* Blog */
        Route::get('/blog',                [Admin\BlogController::class, 'index'])->name('blog.index');
        Route::get('/blog/create',         [Admin\BlogController::class, 'create'])->name('blog.create');
        Route::post('/blog',               [Admin\BlogController::class, 'store'])->name('blog.store');
        Route::get('/blog/{blog}/edit',    [Admin\BlogController::class, 'edit'])->name('blog.edit');
        Route::put('/blog/{blog}',         [Admin\BlogController::class, 'update'])->name('blog.update');
        Route::delete('/blog/{blog}',      [Admin\BlogController::class, 'destroy'])->name('blog.destroy');
        Route::post('/blog/auto-generate', [Admin\BlogController::class, 'autoGenerate'])->name('blog.auto-generate');

        /* Matches */
        Route::get('/matches',        [Admin\MatchAdminController::class, 'index'])->name('matches');
        Route::post('/matches/fetch', [Admin\MatchAdminController::class, 'fetch'])->name('matches.fetch');

        /* Predictions */
        Route::get('/predictions',          [Admin\PredictionAdminController::class, 'index'])->name('predictions');
        Route::post('/predictions/generate',[Admin\PredictionAdminController::class, 'generate'])->name('predictions.generate');

        /* Analytics */
        Route::get('/analytics', [Admin\AnalyticsController::class, 'index'])->name('analytics');

        /* Daily Picks */
        Route::get('/picks',          [Admin\PicksAdminController::class, 'index'])->name('picks');
        Route::post('/picks/refresh', [Admin\PicksAdminController::class, 'refresh'])->name('picks.refresh');
        Route::post('/picks/recheck', [Admin\PicksAdminController::class, 'recheck'])->name('picks.recheck');

        /* Stats */
        Route::get('/stats', [\App\Http\Controllers\Admin\StatsAdminController::class, 'index'])->name('stats.index');

        /* Lineup Picks */
        Route::get('/lineup-picks', [\App\Http\Controllers\Admin\LineupPicksAdminController::class, 'index'])->name('lineup-picks.index');

        /* Correct Score */
        Route::get('/correct-score', [\App\Http\Controllers\Admin\CorrectScoreAdminController::class, 'index'])->name('correct-score.index');

        /* Rollover */
        Route::get('/rollover',                      [\App\Http\Controllers\Admin\RolloverAdminController::class, 'index'])->name('rollover.index');
        Route::post('/rollover/new-challenge',       [\App\Http\Controllers\Admin\RolloverAdminController::class, 'newChallenge'])->name('rollover.new-challenge');
        Route::post('/rollover/select-pick',         [\App\Http\Controllers\Admin\RolloverAdminController::class, 'selectPick'])->name('rollover.select-pick');
        Route::post('/rollover/{pick}/void',         [\App\Http\Controllers\Admin\RolloverAdminController::class, 'voidPick'])->name('rollover.void-pick');

        /* Winners */
        Route::get('/winners',                        [\App\Http\Controllers\Admin\WinnersAdminController::class, 'index'])->name('winners.index');
        Route::post('/winners/{winner}/approve',      [\App\Http\Controllers\Admin\WinnersAdminController::class, 'approve'])->name('winners.approve');
        Route::post('/winners/{winner}/amount',       [\App\Http\Controllers\Admin\WinnersAdminController::class, 'updateAmount'])->name('winners.update-amount');
        Route::delete('/winners/{winner}',            [\App\Http\Controllers\Admin\WinnersAdminController::class, 'reject'])->name('winners.reject');

        /* Newsletter */
        Route::get('/newsletter',                       [Admin\NewsletterAdminController::class, 'index'])->name('newsletter.index');
        Route::get('/newsletter/export',                [Admin\NewsletterAdminController::class, 'export'])->name('newsletter.export');
        Route::post('/newsletter/send-now',             [Admin\NewsletterAdminController::class, 'sendNow'])->name('newsletter.send-now');
        Route::delete('/newsletter/{subscriber}',       [Admin\NewsletterAdminController::class, 'destroy'])->name('newsletter.destroy');

        Route::get('/settings',  [Admin\SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
    });
});
