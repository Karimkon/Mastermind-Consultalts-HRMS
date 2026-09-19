#!/bin/sh
# Drains the mail/notification queue, then exits.
#
# This host has no persistent process supervisor, so the queue is drained by
# short runs rather than a long-lived worker. Two things call this script:
#
#   1. A cron job in hPanel, once a minute (the safety net):
#        * * * * * /bin/sh /home/u895033456/domains/mastermind.autos/queue-run.sh
#   2. App\Services\QueueRunner, right after the app queues a batch of mail,
#      so payslips go out immediately instead of waiting for the next minute.
#
# --stop-when-empty : exit as soon as the queue is drained instead of idling.
# --max-time=50     : never overlap the next minute's cron run.
# --tries=3         : a transient SMTP failure is retried, then lands in failed_jobs.
#
# The lock file makes the script safe to call repeatedly and from both callers at
# once: a second run exits immediately while the first is still sending.

APP_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
LOCK="$APP_DIR/storage/queue-worker.lock"

# Skip if a run is already going, unless its lock is stale (older than 10 minutes).
if [ -f "$LOCK" ] && [ -n "$(find "$LOCK" -mmin -10 2>/dev/null)" ]; then
    exit 0
fi

echo $$ > "$LOCK"
cd "$APP_DIR" || exit 1
/usr/bin/php artisan queue:work --stop-when-empty --max-time=50 --tries=3 --sleep=1 \
    >> storage/logs/queue-worker.log 2>&1
rm -f "$LOCK"
