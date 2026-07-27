// Thin client for the Laravel worker endpoints. Every request carries the
// shared X-Worker-Token header — the secret you set in BOTH .env files.

const BASE = (process.env.TAVS_BASE_URL || '').replace(/\/$/, '');
const TOKEN = process.env.BOOKING_WORKER_TOKEN || '';

function headers() {
  return { 'X-Worker-Token': TOKEN, 'Content-Type': 'application/json', Accept: 'application/json' };
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

/** GET today's betslip spec (the tickets to build). */
export async function fetchSpec() {
  if (!BASE)  throw new Error('TAVS_BASE_URL is not set. Add it as a GitHub Actions secret (e.g. https://tavsscore.com).');
  if (!TOKEN) throw new Error('BOOKING_WORKER_TOKEN is not set. Add it as a GitHub Actions secret (same value as Hostinger .env).');

  let res;
  try {
    res = await fetch(`${BASE}/api/worker/betslip-spec`, { headers: headers() });
  } catch (e) {
    throw new Error(`Could not reach ${BASE} — check TAVS_BASE_URL is your real site URL. (${e.message})`);
  }
  if (!res.ok) throw new Error(explain(res.status, `${BASE}/api/worker/betslip-spec`) + ` — body: ${(await res.text()).slice(0, 200)}`);
  return res.json();
}

/** POST a finished booking code back to the app. */
export async function postCode(payload) {
  const res = await fetch(`${BASE}/api/worker/booking-codes`, {
    method: 'POST',
    headers: headers(),
    body: JSON.stringify(payload),
  });
  if (!res.ok) throw new Error(explain(res.status, `${BASE}/api/worker/booking-codes`) + ` — body: ${(await res.text()).slice(0, 200)}`);
  return res.json();
}
