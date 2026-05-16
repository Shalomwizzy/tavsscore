# TavsScore ⚽

AI-powered football live scores, daily picks, draw picks, GG picks, correct score predictions, and a rollover challenge — built for African football fans.

## Features

- **Live Scores** — real-time match updates via API-Football
- **Daily Picks** — 3 highest-confidence AI picks published every morning at 08:00 Lagos
- **Draw Picks** — up to 5 triple-AI-verified draw predictions per day
- **GG Picks** — up to 5 triple-AI-verified Both Teams Score predictions per day
- **Correct Score** — AI predicted scorelines with Poisson modeling
- **Rollover Challenge** — cumulative accumulator streak feature
- **Lineup Picks** — picks updated when confirmed starting XIs are released
- **African Football** — dedicated coverage of Nigerian, Ghanaian, Kenyan, and other African leagues
- **Triple-AI Consensus** — Groq, Gemini, and Mistral must independently agree for draw/GG picks to qualify
- **Push Notifications** — OneSignal web push for morning picks and outcome results
- **Telegram Channel** — automated morning picks and win/loss notifications
- **Newsletter** — email subscriber list with pick summaries
- **Winners Wall** — community proof-of-win submission and approval system
- **Admin Dashboard** — full admin panel: picks management, analytics, broadcast, blog
- **Stats Page** — full accuracy track record: daily, draw, and GG picks broken down by league and outcome type
- **Blog** — football analysis and news

## Tech Stack

- **Backend** — PHP 8.2 / Laravel 10
- **Database** — MySQL (production), SQLite (testing)
- **AI** — Groq (LLaMA), Google Gemini, Mistral (triple-consensus)
- **Frontend** — Blade, vanilla JS, custom CSS (no frontend framework)
- **Notifications** — OneSignal (push), Telegram Bot API
- **Scheduling** — Laravel scheduler via cron
- **Build** — Vite + npm
- **Testing** — PHPUnit (171 tests, 276 assertions)

## Setup

```bash
cp .env.example .env
composer install
npm install && npm run build
php artisan key:generate
php artisan migrate --seed
```

Required `.env` keys: `APP_URL`, `DB_*`, `FOOTBALL_API_KEY`, `GROQ_API_KEY`, `GEMINI_API_KEY`, `MISTRAL_API_KEY`, `ONESIGNAL_APP_ID`, `ONESIGNAL_REST_API_KEY`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`.

Optional: `ADMIN_SEED_PASSWORD` (overrides the default seeded admin password).

## Scheduled Commands

| Command | Schedule | Purpose |
|---|---|---|
| `picks:select` | 06:00 Lagos | Select daily + draw + GG picks |
| `picks:notify` | 08:00 Lagos | Send morning push + Telegram notifications |
| `predictions:check-outcomes` | Every 5 min | Resolve finished matches, send win/loss alerts |
| `picks:update-lineups` | Every minute | Update picks when lineups are confirmed |

## Live

https://tavsscore.com
