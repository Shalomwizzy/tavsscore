<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CorrectScoreController;
use App\Http\Controllers\HallOfFameController;
use App\Http\Controllers\Over15PicksController;
use App\Http\Controllers\Over25PicksController;
use App\Http\Controllers\RolloverController;
use App\Http\Controllers\Team3PlusController;
use App\Http\Controllers\DoubleChanceController;
use App\Http\Controllers\WinnersController;
use App\Http\Controllers\DailyPickController;
use App\Http\Controllers\DailyFootballPredictionsController;
use App\Http\Controllers\DrawPicksController;
use App\Http\Controllers\GGPicksController;
use App\Http\Controllers\LineupPicksController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\SpecialtyMarketPicksController;
use App\Http\Controllers\TrackRecordController;
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
Route::get('/tennis', [App\Http\Controllers\TennisPredictionController::class, 'index'])->name('tennis.index');
Route::get('/tennis/predictions/{tennisPrediction}', [App\Http\Controllers\TennisPredictionController::class, 'show'])->name('tennis.show');
Route::get('/picks',        [DailyPickController::class, 'index'])->name('picks.index');
Route::get('/daily-football-predictions', [DailyFootballPredictionsController::class, 'index'])->name('daily-football-predictions.index');
Route::get('/draw-picks',   [DrawPicksController::class,  'index'])->name('draw-picks.index');
Route::get('/gg-picks',     [GGPicksController::class,   'index'])->name('gg-picks.index');
Route::get('/lineup-picks',  [LineupPicksController::class, 'index'])->name('lineup-picks.index');
Route::get('/correct-score', [CorrectScoreController::class, 'index'])->name('correct-score.index');
Route::get('/over-1-5',     [Over15PicksController::class,  'index'])->name('over15-picks.index');
Route::get('/over-2-5',     [Over25PicksController::class,  'index'])->name('over25-picks.index');
Route::get('/under-3-5',    [SpecialtyMarketPicksController::class, 'under35'])->name('under35-picks.index');
Route::get('/under-4-5',    [SpecialtyMarketPicksController::class, 'under45'])->name('under45-picks.index');
Route::get('/handicap-picks',[SpecialtyMarketPicksController::class, 'handicap'])->name('handicap-picks.index');
Route::get('/european-handicap-picks',[SpecialtyMarketPicksController::class, 'europeanHandicap'])->name('european-handicap-picks.index');
Route::get('/team-3-plus',    [Team3PlusController::class,    'index'])->name('team3plus-picks.index');
Route::get('/double-chance',  [DoubleChanceController::class,  'index'])->name('double-chance.index');
Route::get('/rollover',           [RolloverController::class, 'index'])->name('rollover.index');
Route::get('/rollover/{date}',    [RolloverController::class, 'show'])->name('rollover.show')->where('date', '\d{4}-\d{2}-\d{2}');
Route::get('/stats',        [StatsController::class, 'index'])->name('stats.index');
Route::get('/goalscorer-picks', [App\Http\Controllers\GoalscorerPicksController::class, 'index'])->name('goalscorer-picks.index');
Route::get('/corners-picks', [App\Http\Controllers\CornersPicksController::class, 'index'])->name('corners-picks.index');
Route::get('/fantasy',      [App\Http\Controllers\FantasyController::class, 'index'])->name('fantasy.index');
Route::get('/standings',    [App\Http\Controllers\LeagueStatsController::class, 'standings'])->name('standings.index');
Route::get('/top-scorers',  [App\Http\Controllers\LeagueStatsController::class, 'topScorers'])->name('top-scorers.index');
Route::get('/track-record', [TrackRecordController::class, 'index'])->name('track-record.index');
Route::get('/results',     [ResultsController::class, 'index'])->name('results.index');

/* ── Booking Codes (public) ── */
Route::get('/booking-codes', [App\Http\Controllers\BookingCodesController::class, 'index'])->name('booking-codes.index');
Route::get('/high-risk', [App\Http\Controllers\HighRiskController::class, 'index'])->name('high-risk.index');

/* ── Winners ── */
Route::get('/winners',                  [WinnersController::class, 'index'])->name('winners.index');
Route::post('/winners/submit',          [WinnersController::class, 'submit'])->middleware('throttle:10,60')->name('winners.submit');
Route::get('/hall-of-fame',             [HallOfFameController::class, 'index'])->name('hall-of-fame.index');
Route::get('/winners/check-username',   [HallOfFameController::class, 'checkUsername'])->name('winners.check-username');

/* ── Football News ── */
Route::get('/football-news', [BlogController::class, 'index'])->name('blog.index');
Route::get('/football-news/{year}/{month}/{day}/{slug}', [BlogController::class, 'show'])
    ->whereNumber('year')
    ->where('month', '\d{2}')
    ->where('day', '\d{2}')
    ->where('slug', '[A-Za-z0-9-]+')
    ->name('blog.show');

// Preserve every existing blog URL permanently so Google transfers the old
// pages' signals to the dated football-news format instead of seeing a 404.
Route::get('/blog', [BlogController::class, 'legacyIndex'])->name('blog.legacy-index');
Route::get('/blog/{slug}', [BlogController::class, 'legacyShow'])->name('blog.legacy-show');

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
        Route::post('/blog/{blog}/regenerate-article', [Admin\BlogController::class, 'regenerateArticle'])->name('blog.regenerate-article');
        Route::delete('/blog/{blog}',      [Admin\BlogController::class, 'destroy'])->name('blog.destroy');
        Route::post('/blog/auto-generate', [Admin\BlogController::class, 'autoGenerate'])->name('blog.auto-generate');

        /* Matches */
        Route::get('/matches',        [Admin\MatchAdminController::class, 'index'])->name('matches');
        Route::post('/matches/fetch', [Admin\MatchAdminController::class, 'fetch'])->name('matches.fetch');

        /* Predictions */
        Route::get('/predictions',          [Admin\PredictionAdminController::class, 'index'])->name('predictions');
        Route::post('/predictions/generate',[Admin\PredictionAdminController::class, 'generate'])->name('predictions.generate');
        Route::post('/predictions/rebuild',[Admin\PredictionAdminController::class, 'rebuild'])->name('predictions.rebuild');
        Route::get('/daily-football-predictions', [Admin\DailyFootballPredictionsAdminController::class, 'index'])->name('daily-football-predictions.index');

        /* Tennis (isolated from football) */
        Route::get('/tennis', [Admin\TennisAdminController::class, 'index'])->name('tennis.index');
        Route::post('/tennis/fetch-fixtures', [Admin\TennisAdminController::class, 'fetchFixtures'])->name('tennis.fetch');
        Route::post('/tennis/generate', [Admin\TennisAdminController::class, 'generatePredictions'])->name('tennis.generate');
        Route::post('/tennis/settle', [Admin\TennisAdminController::class, 'settleResults'])->name('tennis.settle');
        Route::get('/tennis/images', [Admin\SettingsController::class, 'tennisMedia'])->name('tennis.media');

        /* Analytics */
        Route::get('/analytics', [Admin\AnalyticsController::class, 'index'])->name('analytics');

        /* Daily Picks */
        Route::get('/picks',          [Admin\PicksAdminController::class, 'index'])->name('picks');
        Route::post('/picks/refresh',      [Admin\PicksAdminController::class, 'refresh'])->name('picks.refresh');
        Route::post('/picks/refresh-draw', [Admin\PicksAdminController::class, 'refreshDraw'])->name('picks.refresh-draw');
        Route::post('/picks/refresh-gg',   [Admin\PicksAdminController::class, 'refreshGG'])->name('picks.refresh-gg');
        Route::post('/picks/rebuild-daily', [Admin\PicksAdminController::class, 'rebuildDaily'])->name('picks.rebuild-daily');
        Route::post('/picks/rebuild-draw', [Admin\PicksAdminController::class, 'rebuildDraw'])->name('picks.rebuild-draw');
        Route::post('/picks/rebuild-gg', [Admin\PicksAdminController::class, 'rebuildGG'])->name('picks.rebuild-gg');
        Route::post('/picks/recheck',      [Admin\PicksAdminController::class, 'recheck'])->name('picks.recheck');
        Route::post('/picks/{prediction}/regenerate', [Admin\PicksAdminController::class, 'regeneratePick'])->name('picks.regenerate');

        /* Stats */
        Route::get('/stats', [\App\Http\Controllers\Admin\StatsAdminController::class, 'index'])->name('stats.index');

        /* Goalscorer Picks (read-only — computed live from player stats) */
        Route::get('/goalscorer-picks', [Admin\GoalscorerPicksAdminController::class, 'index'])->name('goalscorer-picks.index');
        Route::post('/goalscorer-picks/rebuild', [Admin\GoalscorerPicksAdminController::class, 'rebuild'])->name('goalscorer-picks.rebuild');

        /* Fantasy — best XI from player stats */
        Route::get('/fantasy',         [Admin\FantasyAdminController::class, 'index'])->name('fantasy.index');
        Route::post('/fantasy/rebuild',[Admin\FantasyAdminController::class, 'rebuild'])->name('fantasy.rebuild');

        /* API-Football stats — standings, team stats, player stats */
        Route::get('/api-stats',            [Admin\ApiStatsAdminController::class, 'index'])->name('api-stats.index');
        Route::post('/api-stats/standings', [Admin\ApiStatsAdminController::class, 'fetchStandings'])->name('api-stats.standings');
        Route::post('/api-stats/teams',     [Admin\ApiStatsAdminController::class, 'fetchTeams'])->name('api-stats.teams');
        Route::post('/api-stats/players',   [Admin\ApiStatsAdminController::class, 'fetchPlayers'])->name('api-stats.players');

        /* AI Learning — self-calibration dashboard */
        Route::get('/ai-learning',      [Admin\AILearningController::class, 'index'])->name('ai-learning.index');
        Route::post('/ai-learning/recalibrate', [Admin\AILearningController::class, 'recalibrate'])->name('ai-learning.recalibrate');

        /* Shalom AI — isolated first-party shadow model and editorial lab */
        Route::get('/shalom-ai', [Admin\ShalomAIController::class, 'index'])->name('shalom-ai.index');
        Route::post('/shalom-ai/train', [Admin\ShalomAIController::class, 'train'])->name('shalom-ai.train');
        Route::post('/shalom-ai/predict', [Admin\ShalomAIController::class, 'predict'])->name('shalom-ai.predict');
        Route::post('/shalom-ai/settle', [Admin\ShalomAIController::class, 'settle'])->name('shalom-ai.settle');
        Route::post('/shalom-ai/draft', [Admin\ShalomAIController::class, 'draft'])->name('shalom-ai.draft');

        /* Draw Picks */
        Route::get('/draw-picks',          [Admin\DrawPicksAdminController::class, 'index'])->name('draw-picks.index');
        Route::post('/draw-picks/refresh', [Admin\DrawPicksAdminController::class, 'refresh'])->name('draw-picks.refresh');
        Route::post('/draw-picks/rebuild', [Admin\DrawPicksAdminController::class, 'rebuild'])->name('draw-picks.rebuild');

        /* GG Picks */
        Route::get('/gg-picks',          [Admin\GGPicksAdminController::class, 'index'])->name('gg-picks.index');
        Route::post('/gg-picks/refresh', [Admin\GGPicksAdminController::class, 'refresh'])->name('gg-picks.refresh');
        Route::post('/gg-picks/rebuild', [Admin\GGPicksAdminController::class, 'rebuild'])->name('gg-picks.rebuild');

        /* Lineup Picks */
        Route::get('/lineup-picks',          [\App\Http\Controllers\Admin\LineupPicksAdminController::class, 'index'])->name('lineup-picks.index');
        Route::post('/lineup-picks/notify',  [\App\Http\Controllers\Admin\LineupPicksAdminController::class, 'sendNotification'])->name('lineup-picks.notify');
        Route::post('/lineup-picks/rebuild', [\App\Http\Controllers\Admin\LineupPicksAdminController::class, 'rebuild'])->name('lineup-picks.rebuild');

        /* Correct Score */
        Route::get('/correct-score',         [\App\Http\Controllers\Admin\CorrectScoreAdminController::class, 'index'])->name('correct-score.index');
        Route::post('/correct-score/notify', [\App\Http\Controllers\Admin\CorrectScoreAdminController::class, 'sendNotification'])->name('correct-score.notify');
        Route::post('/correct-score/rebuild', [\App\Http\Controllers\Admin\CorrectScoreAdminController::class, 'rebuild'])->name('correct-score.rebuild');

        /* Over 1.5 Picks */
        Route::get('/over15',          [\App\Http\Controllers\Admin\Over15AdminController::class, 'index'])->name('over15.index');
        Route::post('/over15/refresh', [\App\Http\Controllers\Admin\Over15AdminController::class, 'refresh'])->name('over15.refresh');
        Route::post('/over15/rebuild', [\App\Http\Controllers\Admin\Over15AdminController::class, 'rebuild'])->name('over15.rebuild');

        /* Over 2.5 Picks */
        Route::get('/over25',          [\App\Http\Controllers\Admin\Over25AdminController::class, 'index'])->name('over25.index');
        Route::post('/over25/refresh', [\App\Http\Controllers\Admin\Over25AdminController::class, 'refresh'])->name('over25.refresh');
        Route::post('/over25/rebuild', [\App\Http\Controllers\Admin\Over25AdminController::class, 'rebuild'])->name('over25.rebuild');

        /* Under goals & Asian Handicap Picks */
        Route::get('/under35', [\App\Http\Controllers\Admin\SpecialtyMarketPicksAdminController::class, 'under35'])->name('under35.index');
        Route::post('/under35/refresh', [\App\Http\Controllers\Admin\SpecialtyMarketPicksAdminController::class, 'refreshUnder35'])->name('under35.refresh');
        Route::post('/under35/rebuild', [\App\Http\Controllers\Admin\SpecialtyMarketPicksAdminController::class, 'rebuildUnder35'])->name('under35.rebuild');
        Route::get('/under45', [\App\Http\Controllers\Admin\SpecialtyMarketPicksAdminController::class, 'under45'])->name('under45.index');
        Route::post('/under45/refresh', [\App\Http\Controllers\Admin\SpecialtyMarketPicksAdminController::class, 'refreshUnder45'])->name('under45.refresh');
        Route::post('/under45/rebuild', [\App\Http\Controllers\Admin\SpecialtyMarketPicksAdminController::class, 'rebuildUnder45'])->name('under45.rebuild');
        Route::get('/handicap', [\App\Http\Controllers\Admin\SpecialtyMarketPicksAdminController::class, 'handicap'])->name('handicap.index');
        Route::post('/handicap/refresh', [\App\Http\Controllers\Admin\SpecialtyMarketPicksAdminController::class, 'refreshHandicap'])->name('handicap.refresh');
        Route::post('/handicap/rebuild', [\App\Http\Controllers\Admin\SpecialtyMarketPicksAdminController::class, 'rebuildHandicap'])->name('handicap.rebuild');
        Route::get('/european-handicap', [\App\Http\Controllers\Admin\SpecialtyMarketPicksAdminController::class, 'europeanHandicap'])->name('european-handicap.index');
        Route::post('/european-handicap/refresh', [\App\Http\Controllers\Admin\SpecialtyMarketPicksAdminController::class, 'refreshEuropeanHandicap'])->name('european-handicap.refresh');
        Route::post('/european-handicap/rebuild', [\App\Http\Controllers\Admin\SpecialtyMarketPicksAdminController::class, 'rebuildEuropeanHandicap'])->name('european-handicap.rebuild');

        /* Corner Picks */
        Route::get('/corners', [\App\Http\Controllers\Admin\CornersPicksAdminController::class, 'index'])->name('corners.index');
        Route::post('/corners/refresh', [\App\Http\Controllers\Admin\CornersPicksAdminController::class, 'refresh'])->name('corners.refresh');
        Route::post('/corners/rebuild', [\App\Http\Controllers\Admin\CornersPicksAdminController::class, 'rebuild'])->name('corners.rebuild');

        /* Team 3+ Picks */
        Route::get('/team3plus',          [\App\Http\Controllers\Admin\Team3PlusAdminController::class, 'index'])->name('team3plus.index');
        Route::post('/team3plus/refresh', [\App\Http\Controllers\Admin\Team3PlusAdminController::class, 'refresh'])->name('team3plus.refresh');
        Route::post('/team3plus/rebuild', [\App\Http\Controllers\Admin\Team3PlusAdminController::class, 'rebuild'])->name('team3plus.rebuild');
        Route::get('/double-chance',          [\App\Http\Controllers\Admin\DoubleChanceAdminController::class, 'index'])->name('double-chance.index');
        Route::post('/double-chance/refresh', [\App\Http\Controllers\Admin\DoubleChanceAdminController::class, 'refresh'])->name('double-chance.refresh');
        Route::post('/double-chance/rebuild', [\App\Http\Controllers\Admin\DoubleChanceAdminController::class, 'rebuild'])->name('double-chance.rebuild');
        Route::post('/double-chance/notify',  [\App\Http\Controllers\Admin\DoubleChanceAdminController::class, 'notify'])->name('double-chance.notify');

        /* Rollover */
        Route::get('/rollover',                      [\App\Http\Controllers\Admin\RolloverAdminController::class, 'index'])->name('rollover.index');
        Route::post('/rollover/new-challenge',       [\App\Http\Controllers\Admin\RolloverAdminController::class, 'newChallenge'])->name('rollover.new-challenge');
        Route::post('/rollover/select-pick',         [\App\Http\Controllers\Admin\RolloverAdminController::class, 'selectPick'])->name('rollover.select-pick');
        Route::post('/rollover/rebuild-board',        [\App\Http\Controllers\Admin\RolloverAdminController::class, 'rebuildBoard'])->name('rollover.rebuild-board');
        Route::post('/rollover/{pick}/void',         [\App\Http\Controllers\Admin\RolloverAdminController::class, 'voidPick'])->name('rollover.void-pick');
        Route::post('/rollover/{pick}/override',     [\App\Http\Controllers\Admin\RolloverAdminController::class, 'overridePick'])->name('rollover.override-pick');

        /* Winners */
        Route::get('/winners',                        [\App\Http\Controllers\Admin\WinnersAdminController::class, 'index'])->name('winners.index');
        Route::get('/winners/{winner}/edit',          [\App\Http\Controllers\Admin\WinnersAdminController::class, 'edit'])->name('winners.edit');
        Route::put('/winners/{winner}',               [\App\Http\Controllers\Admin\WinnersAdminController::class, 'update'])->name('winners.update');
        Route::post('/winners/{winner}/approve',      [\App\Http\Controllers\Admin\WinnersAdminController::class, 'approve'])->name('winners.approve');
        Route::post('/winners/{winner}/amount',       [\App\Http\Controllers\Admin\WinnersAdminController::class, 'updateAmount'])->name('winners.update-amount');
        Route::delete('/winners/{winner}',            [\App\Http\Controllers\Admin\WinnersAdminController::class, 'reject'])->name('winners.reject');

        /* Booking Code */
        Route::get('/booking-code',      [\App\Http\Controllers\Admin\BookingCodeController::class, 'index'])->name('booking-code.index');
        Route::post('/booking-code/generate', [\App\Http\Controllers\Admin\BookingCodeController::class, 'generate'])->name('booking-code.generate');
        Route::post('/booking-code/send',[\App\Http\Controllers\Admin\BookingCodeController::class, 'send'])->name('booking-code.send');
        Route::post('/booking-code/clear',[\App\Http\Controllers\Admin\BookingCodeController::class, 'clear'])->name('booking-code.clear');
        Route::post('/booking-code/grade',[\App\Http\Controllers\Admin\BookingCodeController::class, 'grade'])->name('booking-code.grade');
        Route::post('/booking-code/resend',[\App\Http\Controllers\Admin\BookingCodeController::class, 'resend'])->name('booking-code.resend');
        Route::delete('/booking-code/{bookingCode}', [\App\Http\Controllers\Admin\BookingCodeController::class, 'destroy'])->name('booking-code.destroy');

        /* High-Risk (auto-built big-odds accumulators) */
        Route::get('/high-risk', [\App\Http\Controllers\Admin\HighRiskAdminController::class, 'index'])->name('high-risk.index');

        /* Broadcast */
        Route::get('/broadcast',      [\App\Http\Controllers\Admin\BroadcastController::class, 'index'])->name('broadcast.index');
        Route::post('/broadcast/send',[\App\Http\Controllers\Admin\BroadcastController::class, 'send'])->name('broadcast.send');

        /* Newsletter */
        Route::get('/newsletter',                       [Admin\NewsletterAdminController::class, 'index'])->name('newsletter.index');
        Route::get('/newsletter/export',                [Admin\NewsletterAdminController::class, 'export'])->name('newsletter.export');
        Route::post('/newsletter/send-now',             [Admin\NewsletterAdminController::class, 'sendNow'])->name('newsletter.send-now');
        Route::delete('/newsletter/{subscriber}',       [Admin\NewsletterAdminController::class, 'destroy'])->name('newsletter.destroy');

        Route::get('/affiliate-links',                                    [Admin\AffiliateLinkController::class, 'index'])->name('affiliate-links.index');
        Route::post('/affiliate-links',                                   [Admin\AffiliateLinkController::class, 'store'])->name('affiliate-links.store');
        Route::put('/affiliate-links/{affiliateLink}',                    [Admin\AffiliateLinkController::class, 'update'])->name('affiliate-links.update');
        Route::delete('/affiliate-links/{affiliateLink}',                 [Admin\AffiliateLinkController::class, 'destroy'])->name('affiliate-links.destroy');
        Route::post('/affiliate-links/{affiliateLink}/toggle',            [Admin\AffiliateLinkController::class, 'toggle'])->name('affiliate-links.toggle');

        Route::get('/homepage-images', [Admin\SettingsController::class, 'homepageMedia'])->name('homepage-media.index');
        Route::get('/settings',        [Admin\SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings',       [Admin\SettingsController::class, 'update'])->name('settings.update');

        Route::get('/pi-ratings',         [\App\Http\Controllers\Admin\PiRatingsAdminController::class, 'index'])->name('pi-ratings.index');
        Route::post('/pi-ratings/rebuild', [\App\Http\Controllers\Admin\PiRatingsAdminController::class, 'rebuild'])->name('pi-ratings.rebuild');

        /* Model Metrics — measurement layer for the DC / Phase-2 ship gate */
        Route::get('/model-metrics', [Admin\ModelMetricsController::class, 'index'])->name('model-metrics.index');

        /* Team Aliases — canonical-name review queue (Phase 1.5.2) */
        Route::get('/team-aliases',                          [Admin\TeamAliasController::class, 'index'])->name('team-aliases.index');
        Route::post('/team-aliases/bulk-approve-unique',     [Admin\TeamAliasController::class, 'bulkApproveUnique'])->name('team-aliases.bulk-approve-unique');
        Route::post('/team-aliases/{alias}/merge',           [Admin\TeamAliasController::class, 'merge'])->name('team-aliases.merge');
        Route::post('/team-aliases/{alias}/approve',         [Admin\TeamAliasController::class, 'approve'])->name('team-aliases.approve');
    });
});
