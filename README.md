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


## Authorization Model & Access Boundaries

The app uses Laravel-native authorization with **role middleware + policies** (no external RBAC package).

### Roles

- `admin`: full access to `/admin/*` routes and admin management workflows.
- `student`: access to student flows (`/quiz/*`, `/history`, `/progress`, `/billing/*`) only.

Role checks are enforced by `role:admin` and `role:student` route middleware (see `EnsureUserHasRole`).

### Route protection boundaries

- **Admin-only areas**: admin dashboard, curriculum CRUD, imports, theory manual review queue, billing administration, and data management routes.
- **Student-only areas**: quiz setup/taking/submission/results, history/progress, and student billing pages.
- **Guest behavior**: unauthenticated users are redirected to login for protected routes (`auth` middleware convention).
- **Wrong-role behavior**: authenticated users with an incorrect role receive `403 Forbidden`.

### Ownership scoping

Policies scope user-owned resources so one student cannot access another student's records:

- `QuizPolicy`: student can view/update only their own quiz attempts.
- `StudentAnswerPolicy`: theory review actions are admin-only; answer view access is owner-or-admin.
- `SubscriptionPaymentPolicy`: student can view only their own payment slip; admin can review all payments.
- Existing subject/topic/question/import policies continue to enforce admin management boundaries.

This ensures route, controller, and model-level authorization stay consistent even when route bindings are used directly.

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

## Billing Access Decision Model (Production)

Quiz access decisions are centralized in `App\Services\Billing\QuizAccessService`.

### Access checks now covered in one place

- `canStartQuiz(User $user, int $questionCount)`
- `canResumeQuiz(User $user, Quiz $quiz)`
- `canSubmitQuiz(User $user, Quiz $quiz)`
- `registerSubmittedQuizUsage(User $user, Quiz $quiz)`

### Start-quiz decision order (current behavior)

1. Admin bypass (allowed).
2. Suspended subscription (blocked).
3. Active subscription (allowed).
4. Free trial available:
   - up to 10 questions allowed;
   - above 10 blocked.
5. Billing configuration safety check (fail-closed when invalid):
   - requires payment settings and at least one active plan.
6. Pending payment with temporary access:
   - allowed while temporary window is valid and daily quota remains;
   - blocked when daily quota is exhausted.
7. Rejected subscription status (blocked).
8. Default no-access state (blocked; student directed to billing).

### Resume/submit decision rules

- Students can resume/submit only their own quizzes.
- Submitted/graded/grading attempts cannot be resumed or resubmitted.
- Existing drafts remain resumable even when new-quiz access is blocked by billing.

### Troubleshooting

- If students are unexpectedly blocked with “Billing unavailable”, verify:
  - `payment_settings` has `bank_account_name`, `bank_account_number`, and `currency`.
  - At least one `subscription_plans` record is active.
- Check application logs for warning entries containing:
  - `Quiz access billing configuration is invalid.`
  - `Quiz access evaluation failed closed.`

See additional operator notes in `docs/billing-access.md`.

## Notes

- Routes and views are intentionally kept backward-compatible with existing Blade pages.
- Inertia/React assets are present in repository, but no product migration to a new frontend stack is performed here.
- AI grading is assistive; low-confidence or malformed responses are routed to manual review.
