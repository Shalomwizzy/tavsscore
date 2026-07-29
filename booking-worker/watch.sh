#!/usr/bin/env bash
# Starts the local listener for Admin → Booking Code → Generate codes on Mac.
# Run once on the Nigerian-IP Mac and leave the terminal open:
#   cd booking-worker && ./watch.sh
cd "$(dirname "$0")"
export PATH="/usr/local/bin:/opt/homebrew/bin:$PATH"
set -a; [ -f .env ] && . ./.env; set +a
exec npm run watch
