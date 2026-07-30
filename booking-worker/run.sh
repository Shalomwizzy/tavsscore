#!/usr/bin/env bash
# Daily SportyBet booking-code run, for a local cron/launchd schedule.
#
# Setup (once):
#   cd booking-worker
#   cp .env.example .env      # then edit .env with your real values
#   npm install
#   chmod +x run.sh
#
# Schedule (crontab -e) — 05:00 daily so every code is booked + pushed before
# 6am (picks are ready ~01:30, rollover 04:30). The built-in retry loop covers a
# late start if the Mac isn't online yet.
#   0 5 * * * /Users/tavs/Desktop/tavs-score/booking-worker/run.sh
#
# This Mac must be on a Nigerian (residential) IP — SportyBet blocks datacenter
# and non-NG IPs, so this cannot run on the server or GitHub Actions.
#
# Retries built in: if the Mac isn't online yet (or SportyBet is briefly
# unreachable) keep retrying until the codes are created. Re-runs are safe —
# the post-back is idempotent per platform+slip+day, so it fills in / updates
# rather than duplicating. Set BOOKING_MAX_ATTEMPTS to a positive number only
# when you explicitly want a finite retry limit.
cd "$(dirname "$0")"
export PATH="/usr/local/bin:/opt/homebrew/bin:$PATH"   # so cron finds node/npm
set -a; [ -f .env ] && . ./.env; set +a

ATTEMPTS="${BOOKING_MAX_ATTEMPTS:-0}" # 0 = retry until successful
DELAY="${BOOKING_RETRY_DELAY:-900}"   # 15 minutes between retry attempts
LOG=booking-worker.log

online() { curl -sf -m 15 -o /dev/null "https://www.sportybet.com/ng/sport/football"; }

i=0
while :; do
  i=$((i + 1))
  limit_label="∞"
  [ "$ATTEMPTS" -gt 0 ] && limit_label="$ATTEMPTS"
  echo "=== booking run $(date) — attempt $i/$limit_label ===" >> "$LOG"
  if ! online; then
    echo "offline / SportyBet unreachable; waiting ${DELAY}s" >> "$LOG"
  elif npm start >> "$LOG" 2>&1; then
    echo "success on attempt $i" >> "$LOG"
    exit 0
  else
    echo "attempt $i failed; retrying in ${DELAY}s" >> "$LOG"
  fi

  if [ "$ATTEMPTS" -gt 0 ] && [ "$i" -ge "$ATTEMPTS" ]; then
    echo "gave up after $ATTEMPTS attempts $(date)" >> "$LOG"
    exit 1
  fi

  sleep "$DELAY"
done
