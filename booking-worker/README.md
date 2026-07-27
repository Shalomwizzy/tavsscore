# TavsScore Booking Code Worker

Turns TavsScore's daily **betslip spec** into **SportyBet / 1xBet booking codes**
and posts them back to the site. It runs a real headless browser (Playwright), so
it **cannot run on Hostinger shared hosting** — it runs on GitHub Actions (free)
or any machine that can run Chromium.

```
Laravel app  ──GET /api/worker/betslip-spec──▶  this worker
(tickets)                                         │ builds codes in a browser
             ◀──POST /api/worker/booking-codes──┘ (SportyBet, then 1xBet)
```

---

## What is `BOOKING_WORKER_TOKEN`? (the thing you asked about)

It is **not** from SportyBet, 1xBet, or any provider. **It is a password you
invent yourself** — one random string that proves "this request really came from
my worker" so nobody else can push fake booking codes to your site.

You put the **same** string in **two** places:

1. **Laravel `.env`** (on Hostinger): `BOOKING_WORKER_TOKEN=<the string>`
2. **The worker** (GitHub Actions secret): `BOOKING_WORKER_TOKEN=<the same string>`

The worker sends it on every request as the `X-Worker-Token` header; Laravel's
`worker.token` middleware checks it matches. If they don't match → `401`.

**Generate one** (any of these):
```bash
openssl rand -hex 32
# or
php -r "echo bin2hex(random_bytes(32));"
```
Copy the output. That's your token. Treat it like a password — don't commit it.

---

## One-time setup

### 1. Laravel side (Hostinger)
Add to `.env` and deploy:
```
BOOKING_WORKER_TOKEN=<your generated token>
```
(`config/services.php` already reads it; the `/api/worker/*` routes are already wired.)

### 2. Worker side (GitHub Actions — recommended)
1. Push this repo to GitHub (the `booking-worker/` folder + `.github/workflows/booking-worker.yml` come with it).
2. Repo → **Settings → Secrets and variables → Actions → New repository secret**, add:
   - `TAVS_BASE_URL` = `https://tavsscore.com`
   - `BOOKING_WORKER_TOKEN` = the **same** token you put in Laravel
3. The workflow runs daily at 13:30 Lagos (`cron: '30 12 * * *'` UTC). You can
   also trigger it manually from the **Actions** tab (“Run workflow”).

### 3. Fill in the SportyBet selectors
The orchestration (fetch spec → build → post) is complete. The only site-specific
part is in [`src/adapters/sportybet.js`](src/adapters/sportybet.js) — the two
functions `addSelection()` and `readBookingCode()` need real DOM selectors.
Run locally with the browser visible to inspect them:
```bash
cd booking-worker
cp .env.example .env      # fill in TAVS_BASE_URL + BOOKING_WORKER_TOKEN
npm install
HEADLESS=false npm start
```
Then `1xbet` later — enable it in `src/adapters/onexbet.js` once SportyBet works.

---

## The tickets it builds

The spec exposes one ticket per market, each built from the **safest** model
selections first and stacked to a combined-odds band of **3.00–500.00**
(minimum 3 legs):

| ref | market |
|---|---|
| `over-1-5` | Over 1.5 Goals |
| `over-2-5` | Over 2.5 Goals |
| `gg` | Both Teams to Score |
| `double-chance` | Double Chance (safest of 1X / X2 per game) |
| `draw-no-bet` | Draw No Bet (safest side per game) |
| `under-3-5` | Under 3.5 Goals |
| `safe-builder` | Mixed — the single safest market on each game |
| `daily-acca` | The site's ranked headline picks |
| `rollover` | Today's rollover ticket |

A market with fewer than 3 safe qualifying games that day is simply skipped.

> ⚠️ Booking codes are informational. This is not betting advice; TavsScore takes
> no bets and handles no money.
