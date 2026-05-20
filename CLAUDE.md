# TavsScore — CLAUDE.md

## Stack
- **Laravel 10 / PHP 8.2** — standard MVC, Blade templates
- **MySQL** — primary database
- **API-Football** — live scores, fixtures, lineups, odds
- **Timezone** — all business logic uses `Africa/Lagos` (WAT, UTC+1). Always pass this to Carbon / `now()`.

## AI prediction pipeline (triple validation)
1. **Poisson model** — calculates expected goals from attack/defensive records → baseline probabilities
2. **Groq (LLaMA)** — receives Poisson output + match context → first prediction + rationale
3. **Google Gemini** — independent cross-check of Groq's output
4. **Mistral AI** — second independent cross-check

Predictions only go out when all three AI outputs agree. If they disagree, the pick is held back or flagged as low-confidence.

Only match data (team names, league, date, statistics) is ever sent to AI services — no user data.

## Pick types (8 specialist markets)

| Route | Controller | Market |
|---|---|---|
| `/draw-picks` | `DrawPicksController` | Draw result |
| `/gg-picks` | `GGPicksController` | Both teams to score |
| `/lineup-picks` | `LineupPicksController` | Confirmed-lineup AI re-run |
| `/over-1-5` | `Over15PicksController` | Over 1.5 goals |
| `/over-2-5` | `Over25PicksController` | Over 2.5 goals |
| `/double-chance` | `DoubleChanceController` | Double chance |
| `/correct-score` | `CorrectScoreController` | Exact scoreline |
| `/team-3-plus` | `Team3PlusController` | One team scores 3+ |

All 8 controllers use the `ResolvesDateNav` trait (`app/Http/Controllers/Concerns/ResolvesDateNav.php`) and pass `$dateMeta` to their views. All views include `@include('partials.date-nav')` for calendar history navigation.

**Max picks per type per day:** 10 (ordered by confidence desc)

## Lineup picks — how they work
- `UpdateLineupPredictions` command runs **every minute** (`picks:update-lineups`)
- `LineupService::isWithinWindow()` checks match is within 180 min before → 20 min after kickoff
- Once a lineup is confirmed and AI re-runs, pick has `has_lineup = true`
- `LineupPicksController` auto-resolves `was_correct` for finished matches using `PickHelpers::resolveOutcome()`

## Rollover Challenge
- 10-day hypothetical accumulator. Stakes are illustrative, not real.
- Model: `RolloverChallenge` (has many `RolloverPick` → `FootballMatch`)
- Pick selection: `rollover:select` at **10:30 Lagos daily** — runs after the 14:00 odds fetch so `impliedOdds()` has real bookmaker prices
- Controller: `RolloverController` — shows current challenge + last 5 completed challenges as collapsible accordions
- Route supports `/rollover/{date}` for date-based navigation

## Cron schedule (all times Africa/Lagos)
```
01:30  clear-quota-flag       — resets API-Football daily quota cache
03:00  picks:select --force   — primary pick selection
03:30  picks:notify daily     — OneSignal + Telegram for main picks
03:40–04:30  picks:notify {type}  — staggered specialty notifications
05:00, 08:00, 10:00  picks:select  — silent re-runs (cache-guarded)
08:00  picks:notify {types}   — backup notifications (guard prevents double-send)
08:30  blog:auto-post         — Groq-generated blog post via AutoBlogPost
09:00  newsletter:send-daily  — top 3 picks to confirmed email subscribers
10:00, 14:00  picks:fetch-closing-odds  — bookmaker odds for rollover
10:30  rollover:select        — today's rollover pick
23:00  results:send-telegram  — daily results summary to Telegram
every 1 min   fetch:matches   — when live matches exist
every 15 min  fetch:matches   — when no live matches
every 1 min   picks:update-lineups  — confirmed lineup re-runs
every 5 min   predictions:check-outcomes
every 15 min  predict:matches
1st of month 02:00  calibration:snapshot
```

## Key patterns

### Cache guard — prevents double notifications
```php
Cache::remember("picks_sent_{$type}_{$date}", 86400, fn () => true)
```
Once a type is notified, later `picks:select` runs cannot overwrite those picks.

### ResolvesDateNav trait
```php
$date     = $this->resolveDate($request->query('date'), $tz);
$dateMeta = $this->buildDateMeta($date, $tz, 'route.name');
```
Validates date is not in the future, not more than 365 days ago. Returns Carbon at start of day in Lagos timezone.

### PickHelpers::resolveOutcome()
`app/Support/PickHelpers.php` — resolves win/loss for any market given a pick and its match's final score. Called by all controllers when grading finished matches.

## Blog images
- Hero images use Picsum Photos seeded URLs: `https://picsum.photos/seed/{keyword}/1200/630`
- No auth required, stable per seed string
- `AutoBlogPost` strips AI-generated `<img>` and `<a>` tags from Groq article HTML before saving

## Notifications
- **OneSignal** — push notifications (device token only, no personal data)
- **Telegram bot** — picks and results to public channel

## Models (key)
- `FootballMatch` — match data, live scores, lineups
- `Prediction` — AI prediction per match; has `has_lineup`, `was_correct`, `confidence`, `predicted_outcome`, `tips` (JSON), `likely_scores` (JSON), `analysis`
- `DailyPick` — curated pick for specialty markets; has `type`, `pick_date`
- `RolloverChallenge` / `RolloverPick` — 10-day challenge
- `BlogPost` — articles, `is_ai_generated` flag

## Conventions
- All pick views display results: `result-win` / `result-loss` CSS classes, score badge, win/loss emoji
- Date navigation: all 8 specialty pages support `?date=YYYY-MM-DD` query string
- Auto-select only runs when `$dateMeta['is_today']` is true — never overwrites archive dates
- Comments: only when WHY is non-obvious. No multi-line blocks.
- No unused feature flags or backwards-compat shims.
