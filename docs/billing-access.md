# Billing & Quiz Access Troubleshooting

## Central Decision Point

All quiz billing access checks run through `App\Services\Billing\QuizAccessService`.

- start: `canStartQuiz`
- resume: `canResumeQuiz`
- submit: `canSubmitQuiz`

Routes/controllers/middleware should consume this service instead of duplicating conditional logic.

## Access Outcomes

Common `reason` values returned by the service:

- `active_subscription`
- `free_trial`
- `pending_verification`
- `daily_limit_reached`
- `temporary_access_expired`
- `payment_rejected`
- `account_suspended`
- `no_active_access`
- `billing_configuration_invalid`

Each includes a student-safe message for UI/overlay rendering.

## Current Flow Map

### Who can start quizzes

- Active subscriptions.
- Free-trial students (max 10 questions).
- Students with pending payment and active temporary access + remaining daily quota.
- Admins bypass start checks (existing behavior).

### Who can continue drafts

- Owner of the draft quiz, if quiz is not submitted/grading/graded.
- Billing blocks on *new quiz starts* do not block resuming existing drafts.

### Who can submit quizzes

- Owner of non-finalized quiz.
- Finalized quizzes are not submittable again.

### Who sees billing prompts

- Any blocked start/submission check redirects to subscription page with an overlay.
- Builder and subscription pages render explicit reason-based guidance.

### Temporary access behavior

- Activated on payment submission.
- Expires at `temporary_access_expires_at`.
- Expired temporary access blocks new quizzes with a message to upload a new payment proof.

### Daily quiz quota behavior

- Quota is tracked per payment/day in `daily_quiz_usages`.
- Usage increments on submitted quizzes with `billing_access_type = temporary_pending_payment`.
- Reaching the per-day quota blocks new starts until next day.

## Fail-Closed Safety

When paid access decisions depend on billing configuration:

- Missing/invalid payment settings OR no active plans cause blocked response (`billing_configuration_invalid`).
- Service logs anomaly via warning/error for admins.
- Student receives generic non-sensitive guidance.
