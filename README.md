# O'Level Study Help App

A Laravel 10 study platform with separate **student** and **admin** experiences, queue-backed grading/import pipelines, and role-guarded billing/access controls.

## Architecture (Current, from code)

- **Backend:** Laravel 10, PHP 8.1+, Eloquent, Form Requests, Policies, middleware-based role/access gates.
- **UI layer:** Server-rendered **Blade** is the primary app UI (`resources/views/pages/**`, `resources/views/layouts/**`).
- **Frontend assets:** Vite + vanilla JS modules for guided flows, quiz interactions, overlays.
- **Inertia/React presence:** Packages and auth-related pages exist, but core student/admin product workflows are currently Blade-driven.
- **Async processing:** Laravel queues for theory grading and import processing.
- **Realtime:** Broadcast events/channels are implemented (Pusher protocol; Reverb-compatible).
- **AI grading:** OpenAI Responses API integration for theory/structured responses with manual-review fallbacks.
- **Billing/access:** Subscription and payment-verification workflows gate quiz access.

## Key Workflows

### Student workflow
1. Student signs in.
2. Chooses level(s), subject(s), optional topic filters, quiz mode, count.
3. Starts quiz and answers with autosave.
4. Submits quiz:
   - MCQ graded immediately.
   - Theory/structured answers queued for grading.
5. Views results/progress/history; grading can continue asynchronously.

### Admin workflow
1. Manages subjects, topics, questions.
2. Publishes/unpublishes question inventory.
3. Imports curriculum/questions via admin import flows (with queued processing).
4. Reviews theory grading outcomes and applies manual overrides.
5. Manages billing plans/discounts/settings and payment verification.

## Feature Matrix (Current Support)

| Area | Status | Notes |
|---|---|---|
| Student quiz flow | ✅ Supported | Setup, attempt, autosave, submit, results/history/progress. |
| MCQ questions | ✅ Supported | Immediate grading on submit. |
| Theory questions | ✅ Supported | Queue-based AI grading + manual review fallback. |
| Structured response questions | ✅ Supported | Included in theory-like grading bucket. |
| Quiz grading states | ✅ Supported | `draft`, `in_progress`, `submitted`, `grading`, `graded`. |
| Admin subject/topic/question management | ✅ Supported | CRUD + bulk actions in admin routes. |
| Imports | ✅ Supported | CSV + JSON curriculum import flows; queued processing. |
| Manual theory review | ✅ Supported | Admin review queue + override action. |
| Billing/subscription/access gating | ✅ Supported | Middleware + services enforce trial/subscription/payment logic. |
| Websocket realtime UX | ✅ Optional enhancement | Broadcast events exist; correctness still DB/HTTP-backed. |

## Quick Setup

1. Install dependencies

```bash
composer install
npm install
```

2. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

3. Run database setup

```bash
php artisan migrate --seed
```

4. Build assets

```bash
npm run build
# or npm run dev
```

5. Run application + workers

```bash
php artisan serve
php artisan queue:work --queue=default
```

Recommended separated workers when using dedicated queues:

```bash
php artisan queue:work --queue=imports
php artisan queue:work --queue=grading
```

## Production Readiness

A production deployment should include all of the following services/processes:

- **Database**: MySQL/MariaDB (or equivalent supported DB).
- **Queue worker(s)**: required for theory grading and import processing.
- **Scheduler**: required for subscription enforcement and abandoned-quiz cleanup.
- **Cache**: Redis recommended (especially with queue/broadcast workloads).
- **Mail**: SMTP/provider config for notification and account flows.
- **Broadcast/Websocket layer**: optional for correctness, but required for live progress UX.

### Scheduler jobs currently required

- `subscriptions:enforce` (hourly)
- `quizzes:cleanup-abandoned` (every 5 minutes)

Set up cron (or equivalent) to run:

```bash
php artisan schedule:run
```

### Queue-backed pipelines currently required

- `GradeTheoryAnswerJob` (AI grading)
- `ProcessQuestionImportJob` (import row processing)

If queue workers are down, grading/import status will remain pending/manual-review until workers resume.

## Known Operational Dependencies

### Environment variables that matter operationally

- **Database:** `DB_*`
- **Queue/cache/session:** `QUEUE_CONNECTION`, `CACHE_DRIVER`, `SESSION_DRIVER`, `REDIS_*`
- **Mail:** `MAIL_*`
- **AI grading:** `OPENAI_*`
- **Imports:** `IMPORT_PROCESSING_QUEUE`
- **Broadcasting:** `BROADCAST_DRIVER`, `PUSHER_*` and/or `REVERB_*`, plus `VITE_PUSHER_*` for frontend subscriptions

### Runtime components

- Queue workers must be supervised (systemd/Supervisor/Horizon pattern).
- Scheduler must run continuously.
- Failed jobs/logs should be monitored (`failed_jobs`, app logs).

## Developer Checklist

Before testing a feature locally, run:

- `php artisan migrate --seed`
- `php artisan queue:work --queue=default` (or split queues)
- `php artisan schedule:work` (or ensure scheduler runner is active)
- `php artisan test`

## Test Command

```bash
composer test
# equivalent: php artisan test
```

## Notes

- Routes and views are intentionally kept backward-compatible with existing Blade pages.
- Inertia/React assets are present in repository, but no product migration to a new frontend stack is performed here.
- AI grading is assistive; low-confidence or malformed responses are routed to manual review.
