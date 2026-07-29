# TavsScore Booking Code Worker

Turns TavsScore's daily **betslip spec** into **SportyBet / 1xBet booking codes**
and posts them back to the site. It runs a real headless browser (Playwright), so
it **cannot run on Hostinger shared hosting**. Run it on the configured local
Mac, which has a browser and the required Nigerian network access.

The worker retries each ticket up to `BOOKING_SLIP_MAX_ATTEMPTS` (default: 8).
If SportyBet still cannot make a usable ticket, no `FAILED-*` record is saved;
the local retry runner continues until it succeeds. Set a positive
`BOOKING_MAX_ATTEMPTS` only if you deliberately want it to stop after a limit.

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
2. **The local Mac worker `.env`**: `BOOKING_WORKER_TOKEN=<the same string>`

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

### 2. Worker side (the local Mac)
In `booking-worker/.env`, set:
```
TAVS_BASE_URL=https://tavsscore.com
BOOKING_WORKER_TOKEN=<the same token as Hostinger>
```
Use `./run.sh` to create the tickets. It retries until every eligible ticket
gets a real code, without adding failed placeholder rows to the website.

### 3. Test the whole pipeline first (no bookmaker needed)
Before touching any bookmaker, prove the plumbing works with the built-in
**mock adapter** — it "books" the safest legs and posts a `MOCK-…` code back,
so you can watch it appear on `/booking-codes`:
```bash
cd booking-worker
cp .env.example .env      # fill in TAVS_BASE_URL + BOOKING_WORKER_TOKEN
npm install
DRY_RUN=true npm start    # no browser launched, posts mock codes
```
If you see codes on the site's booking-codes page, your token + endpoints are
correct and only the SportyBet selectors remain.

### 4. Fill in the SportyBet selectors
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
selections first and stacked to a combined-odds band of **2.00–500.00**
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
