#!/usr/bin/env bash
# SportyBet booking-code generation, designed to run EVERY MINUTE via launchd
# (com.tavsscore.booking-run.plist). It retries every minute until it succeeds,
# then no-ops for the rest of the day — so the moment the Mac is online after
# 01:30 (picks) / 04:30 (rollover), the codes generate and publish on their own.
#
# Setup (once):
#   cd booking-worker && cp .env.example .env   # fill in real values
#   npm install && chmod +x run.sh
#   cp com.tavsscore.booking-run.plist ~/Library/LaunchAgents/
#   launchctl load ~/Library/LaunchAgents/com.tavsscore.booking-run.plist
#
# Must run on a Nigerian residential IP — SportyBet blocks datacenter/non-NG IPs.
cd "$(dirname "$0")"
export PATH="/usr/local/bin:/opt/homebrew/bin:$PATH"   # so launchd/cron find node/npm
set -a; [ -f .env ] && . ./.env; set +a

LOG=booking-worker.log
MARKER=".generated-$(date +%F)"

# Already generated today → nothing to do (this runs every minute).
[ -f "$MARKER" ] && exit 0

# Tidy old day markers so they don't pile up.
find . -maxdepth 1 -name '.generated-*' -mtime +2 -delete 2>/dev/null || true

# Skip cheaply (no browser launch) if offline / SportyBet unreachable — retry next minute.
if ! curl -sf -m 15 -o /dev/null "https://www.sportybet.com/ng/sport/football"; then
  echo "$(date '+%F %T') offline / SportyBet unreachable — will retry next minute" >> "$LOG"
  exit 0
fi

echo "=== booking run $(date '+%F %T') ===" >> "$LOG"
OUT="$(npm start 2>&1)" || true
echo "$OUT" >> "$LOG"

# Mark done only once at least one code actually posted (✓). Otherwise the spec
# may not be ready yet (predictions still generating) — keep retrying.
if printf '%s' "$OUT" | grep -q "✓"; then
  touch "$MARKER"
  echo "$(date '+%F %T') success — codes posted, done for today" >> "$LOG"
else
  echo "$(date '+%F %T') no code posted yet — will retry next minute" >> "$LOG"
fi
