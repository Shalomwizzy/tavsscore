# TavsScore — CLAUDE.md

## Stack
- **Laravel 10 / PHP 8.2** — standard MVC, Blade templates
- **MySQL** — primary database
- **API-Football** — live scores, fixtures, lineups, odds
- **Timezone** — all business logic uses `Africa/Lagos` (WAT, UTC+1). Always pass this to Carbon / `now()`.

## AI prediction pipeline (four-AI, Claude arbiter)
1. **Poisson / Dixon-Coles** — expected goals from attack/defensive records → baseline probabilities + the full joint score matrix.
2. **Groq (LLaMA)** — primary: receives Poisson output + full context → first prediction, numbers, analysis, tips. Free/fast, runs on the whole slate.
3. **Google Gemini** — independent vote on the outcome.
4. **Mistral AI** — independent vote on the outcome.
5. **Claude (Anthropic) — ARBITER** ([ClaudeService::finalVerdict](app/Services/ClaudeService.php)): reviews the match data AND all three AI opinions, then issues the **final** market + confidence (may override the panel, with a stored rationale). Config: `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL` (default `claude-opus-4-8`).

**Resilience — predictions never stop.** If Claude is down/over-limit → falls back to the raw Groq+Gemini+Mistral consensus calibration. If Groq fails → Gemini→Mistral→Claude generate the base. If all fail → neutral Poisson. See [annotateWithGeminiConsensus](app/Services/PredictionService.php).

Only match data (team names, league, date, statistics) is ever sent to AI services — no user data.

**Consensus fields written to the `tips` JSON** per prediction: `gemini_tip`, `mistral_tip`, `claude_tip`, `*_conf`, `gemini_agrees`, `decided_by` (`claude` when the arbiter ruled), `claude_rationale`, `agreement_level` (strong/partial/conflict/arbiter-call/unverified/speculative). Picks with `gemini_agrees=false` are excluded from selection; strong-consensus + high board-conviction picks are ranked first via `pickQualityMultiplier`.

**How the numbers are composed**: Groq owns 1X2; Over 2.5 & BTTS are a 50/50 Groq/Poisson average; Over 1.5/3.5 are Poisson; Dixon-Coles overrides shipped leagues. `predicted_outcome` = the arbiter's final `tips[0].market`.

## Market engine (103+ markets)
[MarketEngine](app/Services/Markets/MarketEngine.php) derives the full betting board from the Dixon-Coles score matrix (pure math, no AI): 1X2, double chance, DNB, over/under 0.5-5.5, BTTS, clean sheets, win-to-nil, exact totals, multigoals, team totals, **handicaps, winning margin, result/BTTS/O-U combos, half-time, HT/FT** — 103 markets. [EventMarketEngine](app/Services/Markets/EventMarketEngine.php) adds corners + cards (Poisson off `fixture_statistics`, via [TeamEventAverages](app/Support/TeamEventAverages.php)). [PlayerScorerModel](app/Services/Markets/PlayerScorerModel.php) powers Goalscorer Picks.

- The board is stored on `predictions.market_board` (JSON) for **every** prediction (incl. cached ones) and shown on the prediction page + `/admin/predictions`.
- **Fed to all four AIs** via [MatchStatsContext](app/Support/MatchStatsContext.php) (standings + season stats + injuries + API-Football's own prediction) plus the ranked board block, so each AI picks its tip from the whole board, not a fixed shortlist.

## Pick types (specialist markets)

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
| `/goalscorer-picks` | `GoalscorerPicksController` | Anytime goalscorer (player stats; computed on the fly, no date-nav) |

Also public: `/standings`, `/top-scorers` (`LeagueStatsController`). The 8 core specialty controllers use the `ResolvesDateNav` trait (`app/Http/Controllers/Concerns/ResolvesDateNav.php`) and pass `$dateMeta` to their views. All views include `@include('partials.date-nav')` for calendar history navigation.

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
- **Safety-first selection** ([RolloverService](app/Services/RolloverService.php)): a leg must clear an **80% model board-probability floor** (`MIN_BOARD_PROB`) — the real "will it win" metric, read from `predictions.market_board` — AND have 4-AI agreement. Candidates are ranked safest-first by board probability, with market diversity vs recent days.
- Controller: `RolloverController` — current challenge + last 5 completed as collapsible accordions; `/rollover/{date}` for navigation

## Cron schedule (all times Africa/Lagos)
```
01:30  clear-quota-flag       — resets API-Football daily quota cache
01:35  results:catch-up       — FIRST after reset: re-fetch missed past results + settle all pending outcomes (14-day)
02:30  stats:fetch-standings  — league tables (before pick selection, so picks use same-day tables)
02:40  stats:fetch-fixture-intel  — injuries + API-Football predictions for next 48h
03:00  picks:select --force   — primary pick selection
03:15  dc:shadow-log          — Dixon-Coles shadow predictions
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
06:00  stats:fetch-team-stats  (via stats:fetch-teams, Mon/Thu 06:20)
07:00  stats:fetch-fixture-stats  — shots/corners/cards/xG for finished fixtures
09:30  stats:fetch-fixture-intel  — second injuries/predictions pass
Sun 20:00  stats:fetch-players    — player stats (quota-heavy; powers Goalscorer Picks)
Tue 21:00  stats:fetch-team-meta  — transfers + coaches (blog news + manager context)
1st of month 02:00  calibration:snapshot
```

## API-Football data ingestion
Beyond fixtures/lineups/odds, dedicated quota-aware fetchers ([ApiFootball\Client](app/Services/ApiFootball/Client.php) mirrors the FootballService back-off; each stops when the daily quota flag trips) populate:

| Command | Tables | Feeds |
|---|---|---|
| `stats:fetch-standings` | `standings` | league tables, rollover floor, AI context |
| `stats:fetch-teams` | `team_statistics` | season form → AI context |
| `stats:fetch-players` | `player_statistics` | Top Scorers page, Goalscorer Picks |
| `stats:fetch-fixture-intel` | `match_injuries`, `api_predictions` | injuries + API-Football's own prediction → AI context |
| `stats:fetch-fixture-stats` | `fixture_statistics` | corners/cards markets, training |
| `stats:fetch-team-meta` | `transfers`, `coaches` | blog news, manager context |

Admin dashboard for all of it: `/admin/api-stats` (counts + per-league standings/team-stats/top-scorers + per-league fetch buttons).

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

## Dixon-Coles engine (Phase 2 + 4)

Statistical bivariate-Poisson model with low-score correction and time-decay weighted MLE. Owns all its own numbers — no LLM involvement in probability generation. Currently shadow-logged as `dc-v1.0`; walk-forward backtest determines per-league ship gate.

**Storage**: `dc_league_params` (γ home advantage, ρ DC correction, half-life, training-set metadata) + `dc_team_params` (α attack, β defense per team, `is_shrunk` flag for sparse-data teams).

**Fit**: [DixonColes\Fitter](app/Services/DixonColes/Fitter.php) does numerical MLE via gradient ascent on the mean time-weighted log-likelihood. Bayesian shrinkage for teams with <10 matches. Per-league fit takes ~2-9 seconds depending on iterations + training-set size. Weekly refit cron target.

**Predict**: [DixonColes\Predictor](app/Services/DixonColes/Predictor.php) → for a fixture, load params, compute λ_home = exp(α_home + β_away + γ), λ_away = exp(α_away + β_home), build joint score matrix, derive 1X2 / O-U / BTTS / top scores. Returns null (NO_PREDICTION) rather than fabricating when params are missing.

**Team name normalization**: [DixonColes\TeamNameNormalizer](app/Services/DixonColes/TeamNameNormalizer.php) folds diacritic/case/numeric-prefix variants (Bayern München = Bayern Munchen, Vfl = VfL Bochum) so training doesn't fragment. Cross-language canonicalization (Bayern Munich ↔ Bayern München) still needs proper alias mapping.

**Commands**:
- `matches:backfill` — pulls full-season historical fixtures per league (Phase 1.3)
- `dc:fit` — trains + persists params per league
- `dc:shadow-log` — writes `dc-v1.0` predictions into `prediction_logs` for upcoming fixtures (not displayed)
- `dc:backtest` — walk-forward monthly refit + prediction against `naive-league-avg-v0-backtest` for ship-gate decisions

**Ship gate**: for each league, DC promotes from shadow to live only when its backtested Brier beats the baseline. Decision recorded in `model_runs.notes`.

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
- `Prediction` — AI prediction per match (operational, UI-driving); has `has_lineup`, `was_correct`, `confidence`, `predicted_outcome`, `tips` (JSON), `likely_scores` (JSON), `market_board` (JSON — 103+ market probabilities), `analysis`
- `PredictionLog` — append-only measurement log; one row per (match, market, model_version, stage). Never read from UI code.
- `ModelRun` — versioned training/refit metadata linked by `model_version`
- `Team` / `TeamAlias` — canonical team reference + provider aliases
- `DcLeagueParams` / `DcTeamParams` — fitted Dixon-Coles parameters per (league, model_version)
- `DailyPick` — curated pick for specialty markets; has `type`, `pick_date`
- `RolloverChallenge` / `RolloverPick` — 10-day challenge
- `Standing` / `TeamStatistic` / `PlayerStatistic` — API-Football league tables, team season stats, player season stats
- `MatchInjury` / `ApiPrediction` — per-fixture injuries/suspensions + API-Football's own model prediction
- `FixtureStatistic` — post-match shots/possession/corners/cards/xG per team
- `Transfer` / `Coach` — team transfers + current managers (blog news / context)
- `BlogPost` — articles, `is_ai_generated` flag (fed real API-Football news, rephrased)

## Conventions
- All pick views display results: `result-win` / `result-loss` CSS classes, score badge, win/loss emoji
- Date navigation: all 8 specialty pages support `?date=YYYY-MM-DD` query string
- Auto-select only runs when `$dateMeta['is_today']` is true — never overwrites archive dates
- Comments: only when WHY is non-obvious. No multi-line blocks.
- No unused feature flags or backwards-compat shims.
