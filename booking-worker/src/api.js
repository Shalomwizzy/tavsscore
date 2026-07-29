// Thin client for the Laravel worker endpoints. Every request carries the
// shared X-Worker-Token header — the secret you set in BOTH .env files.

// Normalise the base URL: trim stray spaces, drop trailing slashes, and add
// https:// if the secret was set without a scheme (e.g. "tavsscore.com").
function normaliseBase(raw) {
  let b = (raw || '').trim().replace(/\/+$/, '');
  if (b && !/^https?:\/\//i.test(b)) b = 'https://' + b;
  return b;
}

const BASE = normaliseBase(process.env.TAVS_BASE_URL);
const TOKEN = (process.env.BOOKING_WORKER_TOKEN || '').trim();

function headers() {
  return {
    'X-Worker-Token': TOKEN,
    'Content-Type': 'application/json',
    Accept: 'application/json',
    // Some shared hosts (incl. Hostinger) block requests with no browser-like
    // User-Agent as suspected bots. Present a normal UA so we're not filtered.
    'User-Agent': 'Mozilla/5.0 (compatible; TavsScoreBookingWorker/1.0)',
  };
}

// Surface the REAL low-level reason behind Node's generic "fetch failed"
// (DNS miss, refused connection, TLS error, timeout) so the log is actionable.
function reachError(base, e) {
  const cause = e?.cause;
  const detail = cause?.code || cause?.message || e?.message || 'unknown';
  let hint = '';
  if (/ENOTFOUND|EAI_AGAIN/.test(detail)) hint = ' → the domain in TAVS_BASE_URL is misspelled or not resolving.';
  else if (/ECONNREFUSED/.test(detail))   hint = ' → the server refused the connection (wrong port/host).';
  else if (/CERT|TLS|SSL/i.test(detail))  hint = ' → SSL certificate problem on the site.';
  else if (/TIMEOUT|ETIMEDOUT/i.test(detail)) hint = ' → the host is not answering GitHub — Hostinger may be blocking datacenter IPs.';
  return new Error(`Could not reach ${base} (${detail})${hint}`);
}

// Turn the raw HTTP result into a plain-English, actionable error so the CI log
// tells you what to fix instead of just "exit code 1".
function explain(status, where) {
  if (status === 401 || status === 403) {
    return `${status} Unauthorized on ${where}. The BOOKING_WORKER_TOKEN in GitHub does not match Hostinger .env — OR the server cached the old config. Fix: make both tokens identical, then on Hostinger run "php artisan config:clear".`;
  }
  if (status === 404) {
    return `404 Not Found on ${where}. The worker routes aren't live on the site yet. Fix: deploy the latest code to Hostinger (git pull + php artisan route:clear), then retry.`;
  }
  if (status >= 500) {
    return `${status} Server error on ${where}. Check the Laravel logs on Hostinger.`;
  }
  return `${status} on ${where}.`;
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// Retry transient network failures (e.g. an intermittent firewall timeout).
// Does NOT retry HTTP errors like 401/404 — those won't fix themselves.
async function fetchWithRetry(url, options, attempts = 3, delayMs = 20000) {
  for (let i = 1; i <= attempts; i++) {
    try {
      return await fetch(url, options);
    } catch (e) {
      if (i === attempts) throw reachError(BASE, e);
      console.warn(`  attempt ${i}/${attempts} failed (${e?.cause?.code || e.message}); retrying in ${delayMs / 1000}s…`);
      await sleep(delayMs);
    }
  }
}

/** GET today's betslip spec (the tickets to build). */
export async function fetchSpec() {
  if (!BASE)  throw new Error('TAVS_BASE_URL is not set. Add it as a GitHub Actions secret (e.g. https://tavsscore.com).');
  if (!TOKEN) throw new Error('BOOKING_WORKER_TOKEN is not set. Add it as a GitHub Actions secret (same value as Hostinger .env).');

  const res = await fetchWithRetry(`${BASE}/api/worker/betslip-spec`, { headers: headers() });
  if (!res.ok) throw new Error(explain(res.status, `${BASE}/api/worker/betslip-spec`) + ` — body: ${(await res.text()).slice(0, 200)}`);
  return res.json();
}

/** POST a finished booking code back to the app. */
export async function postCode(payload) {
  const res = await fetchWithRetry(`${BASE}/api/worker/booking-codes`, {
    method: 'POST',
    headers: headers(),
    body: JSON.stringify(payload),
  });
  if (!res.ok) throw new Error(explain(res.status, `${BASE}/api/worker/booking-codes`) + ` — body: ${(await res.text()).slice(0, 200)}`);
  return res.json();
}

/** Ask the app to push today's booking codes to Telegram + OneSignal (once). */
export async function postNotify() {
  const res = await fetchWithRetry(`${BASE}/api/worker/notify`, { method: 'POST', headers: headers() });
  if (!res.ok) throw new Error(explain(res.status, `${BASE}/api/worker/notify`));
  return res.json();
}
