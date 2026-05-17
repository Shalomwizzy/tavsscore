# TavsScore ⚽

AI-powered football live scores, daily picks, draw picks, GG picks, correct score predictions, and a rollover challenge — built for African football fans.

## Features

- **Live Scores** — real-time match updates via API-Football
- **Daily Picks** — top 3 highest-confidence AI picks published every morning at 08:00 Lagos
- **Draw Picks** — up to 5 triple-AI-verified draw predictions per day
- **GG Picks** — up to 5 triple-AI-verified Both Teams Score predictions per day
- **Over 1.5 / Over 2.5** — Poisson-modelled goals market picks, up to 5 per day
- **Team 3+ NO** — predictions on which team will NOT score 3+ goals, up to 5 per day
- **Correct Score** — top 5 highest-confidence AI predicted scorelines per day (Poisson + Dixon-Coles)
- **Rollover Challenge** — cumulative accumulator streak feature
- **Lineup Picks** — picks updated the moment confirmed starting XIs are released
- **African Football** — dedicated coverage of Nigerian, Ghanaian, Kenyan, and other African leagues
- **Triple-AI Consensus** — Groq (LLaMA), Google Gemini, and Mistral must independently agree for picks to qualify
- **Pi-Ratings** — separate home/away strength ratings per team, updated live after each full-time result
- **Dixon-Coles Correction** — ρ = −0.13 applied to Poisson grid to fix draw underestimation
- **Per-League Calibration** — different draw base rates and confidence thresholds per league (25+ leagues)
- **Match Importance Detection** — late-season, derby, and relegation/title-race context fed to AI
- **xG Home/Away Split** — venue-specific expected goals rates blended 60/40 with overall rates
- **AI Self-Calibration** — adaptive confidence threshold learned from resolved pick history
- **Push Notifications** — OneSignal web push for morning picks, goals, full-time, and outcome results
- **Telegram Channel** — automated morning picks and win/loss notifications
- **Newsletter** — email subscriber list with daily pick summaries
- **Winners Wall** — community proof-of-win submission and approval system
- **Admin Dashboard** — full admin panel: picks management, analytics, pi-ratings, broadcast, blog
- **Stats Page** — full accuracy track record broken down by league, outcome type, and confidence band
- **Blog** — AI-generated and manual football analysis articles

## Tech Stack

- **Backend** — PHP 8.2 / Laravel 10
- **Database** — MySQL (production), SQLite (testing)
- **AI** — Groq (LLaMA), Google Gemini, Mistral (triple-consensus)
- **Frontend** — Blade, vanilla JS, custom CSS (no frontend framework)
- **Notifications** — OneSignal (web push), Telegram Bot API
- **Email** — Laravel Mail / SMTP newsletter
- **Scheduling** — Laravel scheduler via cron
- **Build** — Vite + npm
- **Testing** — PHPUnit (171 tests, 276 assertions)

## Setup

```bash
cp .env.example .env
composer install
npm install && npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed
```

### First-run commands (run once after fresh install or deployment)

```bash
# Seed pi-ratings from existing match history
php artisan piratings:rebuild

# Select today's picks immediately (don't wait for cron)
php artisan picks:select --force
```

### Required `.env` keys

| Key | Purpose |
|---|---|
| `APP_URL` | Full URL of the site |
| `DB_*` | Database connection |
| `FOOTBALL_API_KEY` | API-Football key |
| `GROQ_API_KEY` | Groq (LLaMA) AI |
| `GEMINI_API_KEY` | Google Gemini AI |
| `MISTRAL_API_KEY` | Mistral AI |
| `ONESIGNAL_APP_ID` | OneSignal push |
| `ONESIGNAL_REST_API_KEY` | OneSignal push |
| `TELEGRAM_BOT_TOKEN` | Telegram bot |
| `TELEGRAM_CHAT_ID` | Telegram channel |

### Optional `.env` keys

| Key | Purpose |
|---|---|
| `ADMIN_SEED_PASSWORD` | Override default seeded admin password |
| `GA_ID` | Google Analytics measurement ID |
| `MAIL_*` | SMTP config for newsletter emails |

## All Artisan Commands

### Data ingestion

| Command | Purpose |
|---|---|
| `fetch:matches` | Fetch live, today's and finished fixtures from API-Football. Also fires goal and full-time push notifications, and updates pi-ratings the moment a match reaches FT. |

### Predictions

| Command | Purpose |
|---|---|
| `predict:matches` | Run Poisson + triple-AI (Groq/Gemini/Mistral) predictions for all upcoming matches today. |
| `predictions:check-outcomes {--days=3}` | Compare finished match results against predictions, mark `was_correct`, send correct-score hit notifications. |

### Daily picks selection

| Command | Options | Purpose |
|---|---|---|
| `picks:select` | `--force` re-selects even if picks already exist | Select today's daily picks (top 3), draw picks, GG picks, over 1.5, over 2.5, team 3+, and correct score picks (top 5). Runs multiple times per day — early morning, at pick time, and at midday. |
| `rollover:select` | — | Select today's rollover pick. |

### Notifications

| Command | Options | Purpose |
|---|---|---|
| `picks:notify` | `--type=daily\|draw\|gg\|over15\|over25\|team3plus\|lineup\|correctscore` | Send push + Telegram for the specified pick type. Omit `--type` to send all groups at once. |
| `results:send-telegram` | — | Post today's resolved pick results to Telegram at 23:00 Lagos. |
| `newsletter:send-daily` | — | Email today's 3 daily picks to all confirmed subscribers. |

### Lineup updates

| Command | Purpose |
|---|---|
| `picks:update-lineups` | Re-run AI prediction for all today's matches the moment their confirmed lineup is available. Runs every minute. |

### Odds tracking

| Command | Purpose |
|---|---|
| `picks:fetch-closing-odds` | Fetch near-closing bookmaker odds for today's daily picks to track market movement. Runs twice per day (10:00 and 14:00 Lagos). |

### Pi-Ratings

| Command | Purpose |
|---|---|
| `piratings:rebuild` | Truncate and rebuild ALL team pi-ratings from the full historical match database. Run once after fresh install, then ratings update automatically after each FT result via `fetch:matches`. |

### Blog

| Command | Options | Purpose |
|---|---|---|
| `blog:auto-post` | `--force` overrides existing today's post | Auto-generate a football news article using AI and publish it. |

### Analytics & calibration

| Command | Options | Purpose |
|---|---|---|
| `calibration:snapshot` | `--force` overwrites existing month snapshot | Save a monthly calibration snapshot to track system improvement over time. Runs 1st of each month at 02:00. |

## Scheduled Commands (cron)

Add to server cron:

```
* * * * * cd /path/to/tavsscore && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler then handles:

| Time (Lagos) | Command |
|---|---|
| Every minute | `fetch:matches`, `picks:update-lineups` |
| Every 15 min | `fetch:matches`, `predict:matches` |
| Every 5 min | `predictions:check-outcomes` |
| 00:00 daily | `picks:select --force` (midnight reset) |
| 05:00 daily | `picks:select` |
| 06:00 daily | `picks:notify --type=daily` |
| 06:20 daily | `picks:notify --type=draw` |
| 06:40 daily | `picks:notify --type=gg` |
| 07:00 daily | `picks:notify --type=over15` |
| 07:20 daily | `picks:notify --type=over25` |
| 07:40 daily | `picks:notify --type=team3plus` |
| 08:00 daily | `picks:select` |
| 08:30 daily | `blog:auto-post` |
| 09:00 daily | `newsletter:send-daily` |
| 10:00 daily | `picks:select`, `picks:fetch-closing-odds` |
| 10:30 daily | `rollover:select` |
| 14:00 daily | `picks:fetch-closing-odds` |
| 23:00 daily | `results:send-telegram` |
| 1st of month 02:00 | `calibration:snapshot` |

## Admin Panel

Located at `/admin`. All sections visible in the sidebar:

- **Dashboard** — today's summary stats
- **Analytics** — accuracy trends, market and league performance
- **AI Learning** — adaptive threshold, confidence calibration, cold market detection
- **Pi-Ratings** — team home/away strength rankings + rebuild button
- **Predictions** — all today's AI predictions
- **Daily Picks** — selected top-3 picks
- **Draw Picks** — selected draw picks
- **GG Picks** — selected GG picks
- **Correct Score** — selected top-5 correct score picks
- **Over 1.5 / Over 2.5 / Team 3+** — goals market picks
- **Lineup Picks** — picks updated on confirmed XIs
- **Rollover** — rollover challenge management
- **Matches** — all ingested fixtures
- **Stats** — public accuracy stats
- **Winners Wall** — community win submission approval
- **Blog Posts** — article management
- **Broadcast** — push notification to all subscribers
- **Newsletter** — subscriber list management
- **Settings** — site-wide settings

## Live

https://tavsscore.com
