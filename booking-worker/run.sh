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
set -euo pipefail
cd "$(dirname "$0")"
export PATH="/usr/local/bin:/opt/homebrew/bin:$PATH"   # so cron finds node/npm
set -a; [ -f .env ] && . ./.env; set +a
echo "=== booking run $(date) ===" >> booking-worker.log
npm start >> booking-worker.log 2>&1
