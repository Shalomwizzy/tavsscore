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

**How the numbers are actually composed today** (per [PredictionService.php:176-185](app/Services/PredictionService.php#L176)):
- **1X2 probabilities: 100% Groq.** Poisson is only *fed into* the Groq prompt as context, and only used as a fallback if all 3 LLMs fail.
- **Over 2.5 & BTTS: 50/50 average of Groq and Poisson.**
- **Over 1.5, Over 3.5: 100% Poisson.**
- **`predicted_outcome`: LLM-derived** (from Groq's `tips[0].market`).

The measurement layer (below) calls this baseline `groq-poisson-v0` so future engines have an honest reference point to beat.

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
                                        + logs market-closing rows into prediction_logs
10:30  rollover:select        — today's rollover pick
23:00  results:send-telegram  — daily results summary to Telegram
every 1 min   fetch:matches   — when live matches exist
                              — runs TeamCanonicalizer + FixtureIntegrityService per match
every 15 min  fetch:matches   — when no live matches (same integrity hooks)
every 1 min   picks:update-lineups  — confirmed lineup re-runs
every 5 min   predictions:check-outcomes
                              — also settles prediction_logs (WIN/LOSS/VOID, idempotent)
every 15 min  predict:matches
1st of month 02:00  calibration:snapshot
```

**Not yet scheduled** (run manually or add to Kernel when ready): `coverage:report --days=7` (weekly), `teams:seed` (one-off backfill).

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

## Measurement layer (Phase 1 + 1.5.1)

The operational `predictions` table drives the UI. `prediction_logs` is a **separate append-only log** used strictly for measurement — Brier score, log-loss, calibration, and ship-gate decisions against future models. Never read `prediction_logs` from user-facing code; never write to it directly (use `PredictionLogger`).

**Two write paths:**
- `PredictionObserver` fires on `Prediction::created` and on `Prediction::updated` when `has_lineup` or a pick flag flips true. Writes via [PredictionLogger::logLive()](app/Services/PredictionLogger.php) with `is_backfill=false`. Refuses to log if kickoff has already passed.
- `predictions:seed-logs` command backfills existing `Prediction` rows via `logBackfill()` with `is_backfill=true`. One-off, idempotent per `(match, market, model_version, stage)`.

**Key columns on `prediction_logs`:**
- `model_version` — `groq-poisson-v0` for current pipeline, `market-closing` for bookmaker consensus, future engines get new versions (e.g. `dc-v1.0`). Every version corresponds to a row in `model_runs`.
- `prediction_stage` — `pre_lineup` / `post_lineup`. Lineup reruns produce a distinct `post_lineup` row alongside the original `pre_lineup` one; the dashboard **only compares like-for-like stages** because post-lineup predictions have strictly more information.
- `is_backfill` — flags retroactively materialized rows so they can be excluded from a clean baseline.
- `p_outcome` + `p_home/p_draw/p_away` — 0-1 range decimals, all margin-stripped.
- `actual_result` — `WIN` / `LOSS` / `VOID` (VOID for postponed/canceled/abandoned; excluded from metrics).
- `settled_at` — set by `PredictionLogSettler` inside `predictions:check-outcomes`. Idempotent: `WHERE settled_at IS NULL` guard means re-runs never re-write.

**Market-closing baseline** ([MarketClosingLogger](app/Services/MarketClosingLogger.php)) — bookmaker consensus is the real benchmark. Every model version is measured relative to it on the dashboard. `OddsService::normalisedImpliedProbabilities()` returns fully margin-stripped 1X2 / O2.5 / BTTS by pairing both sides of each binary market.

**Admin dashboard:** `/admin/model-metrics` — Brier / hit rate / log-loss / calibration buckets / Δ-vs-market per (model_version × market × stage). Filters for stage and `is_backfill` inclusion.

## Data integrity (Phase 1.5.2)

**Canonical team reference** — `teams` (canonical name) + `team_aliases` (provider → alias → canonical) tables. `home_team` / `away_team` on `matches` remain free-form strings for now; canonicalization runs in parallel to track and reconcile duplicates.

- [TeamCanonicalizer::resolve()](app/Services/TeamCanonicalizer.php) is **permissive** — unknown names auto-register as unreviewed aliases and log a warning. **Never** blocks fixture ingestion; silent gaps in match data corrupt every downstream model far more than a duplicate canonical team ever would.
- Admin queue lives in `team_aliases` with `reviewed=false`. UI merge tool deferred; inspect via tinker: `TeamAlias::where('reviewed', false)->get()`.

**Fixture integrity checks** ([FixtureIntegrityService](app/Services/FixtureIntegrityService.php)) — run after every `FootballMatch::updateOrCreate` inside `fetch:matches` and `fetch:date`. Flags persisted as an array on `matches.integrity_flags`:
- `duplicate` — same teams within 24h from any provider
- `back_to_back` — either team playing within 48h
- `blowout` — 8+ goals for one side → **sets `held_for_review=true`**
- `result_before_kickoff` — score data present but kickoff still in the future

**`held_for_review` gates downstream:** the settler (`PredictionLogSettler`) and pi-rating updater both skip these matches so corrupt data can't poison metrics or team strength estimates.

**Coverage report** — `php artisan coverage:report --days=7` produces a per-league table: ingested / finished / predicted / coverage% / held / flagged. Warns when held-for-review rate exceeds 5% or prediction coverage drops below 50%.

## Blog images
- Hero images use Picsum Photos seeded URLs: `https://picsum.photos/seed/{keyword}/1200/630`
- No auth required, stable per seed string
- `AutoBlogPost` strips AI-generated `<img>` and `<a>` tags from Groq article HTML before saving

## Notifications
- **OneSignal** — push notifications (device token only, no personal data)
- **Telegram bot** — picks and results to public channel

## Models (key)
- `FootballMatch` — match data, live scores, lineups; has `integrity_flags` (JSON array), `held_for_review` (bool)
- `Prediction` — AI prediction per match (operational, UI-driving); has `has_lineup`, `was_correct`, `confidence`, `predicted_outcome`, `tips` (JSON), `likely_scores` (JSON), `analysis`
- `PredictionLog` — append-only measurement log; one row per (match, market, model_version, stage). Never read from UI code.
- `ModelRun` — versioned training/refit metadata linked by `model_version`
- `Team` / `TeamAlias` — canonical team reference + provider aliases
- `DailyPick` — curated pick for specialty markets; has `type`, `pick_date`
- `RolloverChallenge` / `RolloverPick` — 10-day challenge
- `BlogPost` — articles, `is_ai_generated` flag

## Conventions
- All pick views display results: `result-win` / `result-loss` CSS classes, score badge, win/loss emoji
- Date navigation: all 8 specialty pages support `?date=YYYY-MM-DD` query string
- Auto-select only runs when `$dateMeta['is_today']` is true — never overwrites archive dates
- Comments: only when WHY is non-obvious. No multi-line blocks.
- No unused feature flags or backwards-compat shims.
