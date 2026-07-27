// Thin client for the Laravel worker endpoints. Every request carries the
// shared X-Worker-Token header — the secret you set in BOTH .env files.

const BASE = (process.env.TAVS_BASE_URL || '').replace(/\/$/, '');
const TOKEN = process.env.BOOKING_WORKER_TOKEN || '';

function headers() {
  if (!TOKEN) throw new Error('BOOKING_WORKER_TOKEN is not set');
  return { 'X-Worker-Token': TOKEN, 'Content-Type': 'application/json', Accept: 'application/json' };
}

/** GET today's betslip spec (the tickets to build). */
export async function fetchSpec() {
  const res = await fetch(`${BASE}/api/worker/betslip-spec`, { headers: headers() });
  if (!res.ok) throw new Error(`spec fetch failed: ${res.status} ${await res.text()}`);
  return res.json();
}

/** POST a finished booking code back to the app. */
export async function postCode(payload) {
  const res = await fetch(`${BASE}/api/worker/booking-codes`, {
    method: 'POST',
    headers: headers(),
    body: JSON.stringify(payload),
  });
  if (!res.ok) throw new Error(`code post failed: ${res.status} ${await res.text()}`);
  return res.json();
}
