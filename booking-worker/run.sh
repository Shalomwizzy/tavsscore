#!/usr/bin/env bash
# Daily SportyBet booking-code run, for a local cron/launchd schedule.
#
# Setup (once):
#   cd booking-worker
#   cp .env.example .env      # then edit .env with your real values
#   npm install
#   chmod +x run.sh
#
# Schedule (crontab -e) — 07:00 daily, after picks + rollover are selected:
#   0 7 * * * /Users/tavs/Desktop/tavs-score/booking-worker/run.sh
#
# This Mac must be on a Nigerian (residential) IP — SportyBet blocks datacenter
# and non-NG IPs, so this cannot run on the server or GitHub Actions.
#
# Retries built in: if the Mac isn't online yet (or SportyBet is briefly
# unreachable) the run is retried a few times before giving up. Re-runs are safe
# — the post-back is idempotent per platform+slip+day, so it fills in / updates
# rather than duplicating.
cd "$(dirname "$0")"
export PATH="/usr/local/bin:/opt/homebrew/bin:$PATH"   # so cron finds node/npm
set -a; [ -f .env ] && . ./.env; set +a

ATTEMPTS="${BOOKING_MAX_ATTEMPTS:-8}"
DELAY="${BOOKING_RETRY_DELAY:-900}"   # 15 min between tries → ~2h of retrying
LOG=booking-worker.log

online() { curl -sf -m 15 -o /dev/null "https://www.sportybet.com/ng/sport/football"; }

for i in $(seq 1 "$ATTEMPTS"); do
  echo "=== booking run $(date) — attempt $i/$ATTEMPTS ===" >> "$LOG"
  if ! online; then
    echo "offline / SportyBet unreachable; waiting ${DELAY}s" >> "$LOG"
    sleep "$DELAY"; continue
  fi
  if npm start >> "$LOG" 2>&1; then
    echo "success on attempt $i" >> "$LOG"
    exit 0
  fi
  echo "attempt $i failed; retrying in ${DELAY}s" >> "$LOG"
  sleep "$DELAY"
done

echo "gave up after $ATTEMPTS attempts $(date)" >> "$LOG"
exit 1
