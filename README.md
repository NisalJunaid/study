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
   - Optional presets now prefill this same setup form:
     - Quick Revision
     - Weak Topics Only
     - Mixed Practice
     - Exam Style / Full Practice
     - Continue Recommended Practice
   - Presets do **not** bypass the normal setup pipeline; students can still adjust fields before submit.
3. Starts quiz and answers with autosave.
4. Submits quiz:
   - MCQ graded immediately.
   - Theory/structured answers queued for grading.
5. Views results/progress/history; grading can continue asynchronously.

### Retention + study effectiveness signals (from existing quiz data)

The student progress page now derives practical study guidance directly from submitted quiz/history records:

- **Study streaks**
  - Rule: a day counts when the student has **at least one submitted quiz** (`submitted`, `grading`, or `graded`) with a non-null `submitted_at`.
  - Date boundaries use **UTC calendar days** (current application timezone convention).
  - Exposed metrics: `current streak`, `longest streak`, and whether the streak is active today.

- **Daily study goal**
  - Metric is based on **submitted quizzes per day**.
  - Default goal is `2` quizzes/day (`users.daily_quiz_goal`) and can be edited in Profile.
  - Progress shows completed today, remaining quizzes, and completion percentage.

- **Recommended next practice**
  - Uses existing weak-topic and weak-subject analytics (already derived from quiz answers/scores).
  - Recommendation order:
    1. weak topics first (if enough graded data),
    2. weak subject fallback,
    3. mixed revision fallback when data is sparse.
  - CTA links directly into existing `/quiz/setup` with prefilled filters (subject/topic/mode/count), so no parallel flow is introduced.

### Quiz lifecycle state rules (hardened)

Quiz status transitions are now enforced centrally in `App\Models\Quiz` (`allowedTransitions`, `canTransitionTo`, `transitionTo`).

Allowed transitions:

- `draft -> in_progress -> submitted -> grading -> graded`
- `submitted -> graded` (when no theory-like answers are pending)
- idempotent same-state transitions are allowed (`graded -> graded`) for safe retries

Blocked transitions include:

- `graded -> in_progress`
- `submitted -> draft`
- any other non-declared transition path

Operational notes:

- Quiz submission is row-locked and transactional to prevent duplicate submit races.
- Answer autosave is transactional and blocked once a quiz is submitted/finalized.
- Theory grading jobs only pick answers in `pending` state; retries on already processed items are no-ops.
- Finalization (`FinalizeQuizGradingAction`) recalculates totals and promotes quiz state safely based on remaining `pending/processing` theory answers.

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

## Question Import Rules (Current Behavior)

Question preview/confirm processing now enforces deterministic safety checks before queue processing:

- Required fields are validated per row (`subject`, `type`, `question_text`, `marks`, publish flag + type-specific fields).
- Invalid question types and malformed MCQ/theory/structured payloads are rejected at preview time with row-level messages.
- Subject/topic references are validated against existing curriculum unless "create subjects/topics" toggles are enabled.
- Duplicate detection blocks:
  - duplicate rows in the same file (normalized comparison),
  - duplicates against existing question bank under the same subject/topic/type (exact and normalized prompt checks).
- Duplicate rows are surfaced in preview and skipped safely (they are not silently created).
- Confirm/retry processing is idempotent enough for operational retries: already imported rows are not re-imported, and failed valid rows can be retried.

### Supported question import columns

CSV headers used by the importer:

`subject, topic, type, question_text, difficulty, marks, is_published, option_a, option_b, option_c, option_d, option_e, correct_option, explanation, sample_answer, grading_notes, keywords, acceptable_phrases, question_group_key, part_label, part_prompt, part_marks, part_sample_answer, part_marking_notes`

### JSON format

- Root object must contain a `questions` array.
- Each object mirrors the CSV schema semantics.
- Structured response rows use `structured_parts` and are expanded into preview rows with `question_group_key` + part fields.

Use the Admin → Imports sample download links to get templates that match the current rules exactly.

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


## Theory Grading Reliability (Production)

The AI grading pipeline is hardened for retriable provider failures and manual-review fallback safety:

- `GradeTheoryAnswerJob` now distinguishes retriable vs permanent grading errors.
- Retries restore answer state to `pending` (instead of leaving stale `processing`).
- Terminal failures are safely marked `manual_review` with explicit `manual_review_reason` metadata.
- Admin overrides are audited with before/after metadata.
- Every AI grading attempt/override is logged in `grading_attempts`.

Operational runbook: see `docs/grading-pipeline.md`.

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

### Lifecycle processors by state

- `in_progress`: student autosave via `SaveQuizAnswerAction`.
- `submitted`: `SubmitQuizAction` finalizes attempt records and routes to grading.
- `grading`: `QueueTheoryGradingAction` dispatches `GradeTheoryAnswerJob` batches; each job updates answer grades.
- `graded`: `FinalizeQuizGradingAction` confirms completion timestamps/aggregates when no theory answers remain pending.

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

## Preset documentation

- Student guide: `docs/student-quiz-presets.md`
- Admin notes: `docs/admin-quiz-presets.md`

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
