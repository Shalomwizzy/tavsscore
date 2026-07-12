# Hostinger cron setup — TavsScore

## The one cron entry you need

Laravel's scheduler works via a single **master cron job** that runs every minute. It reads all the `$schedule->command(...)` entries in [app/Console/Kernel.php](../app/Console/Kernel.php) and runs them at the right time.

Once you set this ONE cron, every future scheduling change happens in `Kernel.php` — you never touch the Hostinger control panel again.

### Steps

1. **Log into Hostinger** → your project → **Advanced → Cron Jobs**.
2. Click **Create a Cron Job**.
3. Set:
   - **Common Settings**: `Custom`
   - **Minute**: `*`
   - **Hour**: `*`
   - **Day**: `*`
   - **Month**: `*`
   - **Weekday**: `*`
   - **Command**:
     ```
     cd /home/USERNAME/domains/tavsscore.com/public_html && /usr/bin/php artisan schedule:run >> storage/logs/schedule.log 2>&1
     ```
     Replace `USERNAME` with your Hostinger username. Adjust `/domains/tavsscore.com/public_html` if your document root differs.
4. Save.

That's it. Every minute Hostinger runs `schedule:run`, which internally checks whether any scheduled command is due, and only executes the ones that are.

### Verify it's working

After setup, wait 2 minutes then run in the Hostinger terminal:

```
tail -20 storage/logs/schedule.log
```

You should see entries like `Running scheduled command: [name]` at the correct times. If the file is empty after 5 minutes, the cron isn't running — double-check the path and PHP binary.

You can also inspect what's currently scheduled from the terminal:
```
php artisan schedule:list
```

### Common gotchas on Hostinger

- **`php` not found**: use the full path `/usr/bin/php` or `/opt/alt/php82/usr/bin/php` (whatever your PHP 8.2 binary is). Ask Hostinger support if unclear.
- **Path wrong**: on Hostinger the path is usually `/home/USERNAME/domains/DOMAIN/public_html` — verify via SSH `pwd` when logged in.
- **Storage not writable**: `chmod -R 775 storage bootstrap/cache` if you see permission errors in the schedule log.
- **Timezone mismatch**: Hostinger's server clock may not be Africa/Lagos. Laravel handles this internally because every scheduled command specifies `->timezone('Africa/Lagos')`. Don't try to set the server clock.

---

## What runs when (all times Africa/Lagos)

| Time | Command | Notes |
|---|---|---|
| Every minute | `fetch:matches` | Only when live matches exist |
| Every 15 min | `fetch:matches` | When no live matches |
| Every minute | `picks:update-lineups` | Confirmed-lineup re-runs |
| Every 5 min | `predictions:check-outcomes` | Grade picks + settle prediction_logs |
| Every 15 min | `predict:matches` | Generate AI predictions |
| 01:30 | Clear API quota cache flag | |
| 03:00 | `picks:select --force` | Primary pick selection |
| 03:15 | **`dc:shadow-log --hours-ahead=48`** | **NEW: Dixon-Coles shadow logging** |
| 03:30–04:40 | `picks:notify --type=X` | Staggered notifications |
| 05:00, 08:00, 10:00 | `picks:select` | Silent re-runs (cache-guarded) |
| 08:00 | `picks:notify` (backup) | |
| 08:30 | `blog:auto-post` | |
| 09:00 | `newsletter:send-daily` | |
| 10:00, 14:00 | `picks:fetch-closing-odds` | Now also logs market-closing |
| 10:15 | **`dc:shadow-log --hours-ahead=48`** | **NEW: second DC pass** |
| 10:30 | `rollover:select` | |
| 23:00 | `results:send-telegram` | |
| **Mon 04:00** | **`dc:fit`** | **NEW: weekly Dixon-Coles refit** |
| Sun 07:00 | `coverage:report --days=7` | **NEW: weekly ingestion sanity** |
| 1st of month 02:00 | `calibration:snapshot` | |

---

## Emergency kill switch

If Dixon-Coles predictions start producing garbage in production and you need to revert to the pure Groq+Poisson baseline immediately, add this to `.env` on Hostinger:

```
DC_ENABLED=false
```

Then run:
```
php artisan config:clear
```

Every `PredictionService` run reverts to the pre-DC path within seconds. No code change, no deploy.

---

## First-time deployment checklist

After pushing this branch to Hostinger, run these commands ONCE via SSH:

```bash
php artisan migrate                        # applies all Phase 1 / 1.5 / 2 migrations
php artisan teams:seed                     # backfills team_aliases from existing matches
php artisan matches:backfill --seasons=2021,2022,2023,2024,2025    # ~45 API calls
php artisan dc:fit                         # fits all 9 priority leagues (~90s)
php artisan predictions:seed-logs          # (optional) retroactively logs baseline
```

After that the cron takes over — the model refits itself every Monday morning and shadow-logs every day.
