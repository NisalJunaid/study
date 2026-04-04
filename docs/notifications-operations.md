# Notifications Operations

This app uses Laravel's built-in notification channels (`mail`, `database`) with queued delivery.

## Scheduler + Queue Requirements

- Scheduler must run continuously (`php artisan schedule:run` from cron, or `php artisan schedule:work`).
- Queue workers must process the notifications queue (`NOTIFICATIONS_QUEUE`, defaults to `default`).

Scheduled command:

- `notifications:dispatch-operational` (hourly)
  - dispatches `SendStudentRemindersJob`
  - dispatches `SendAdminOperationalAlertsJob`

## Student reminders

- Inactivity reminders (`STUDY_REMINDER_INACTIVITY_*`)
- Pending verification reminders (`STUDY_REMINDER_PENDING_*`)
- Draft quiz reminders (`STUDY_REMINDER_DRAFT_*`)

All reminders are throttled by per-type cooldowns to prevent duplicate spam on repeated scheduler runs.

## Admin alerts

- Grading failures in recent window.
- Manual review backlog threshold breaches.
- Import failures in recent window.
- Billing/access configuration anomalies.

Admin alerts are also throttled by cooldown (`STUDY_ADMIN_ALERTS_COOLDOWN_HOURS`).
