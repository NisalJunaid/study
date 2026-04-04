# Theory Grading Pipeline (Production Operations)

## Scope

This document covers AI-assisted grading for theory and structured-response answers, including retry behavior, manual review queue handling, and admin override auditability.

## Pipeline overview

1. Student submits quiz (`SubmitQuizAction`).
2. Theory-like answers are set to `pending` and queued via `QueueTheoryGradingAction`.
3. `GradeTheoryAnswerJob` claims each answer into `processing` and calls `TheoryGraderService` in batches.
4. On success:
   - answer becomes `graded` OR `manual_review` (low confidence)
   - `quiz_questions.requires_manual_review` is updated
   - `FinalizeQuizGradingAction` recalculates quiz totals and status.
5. On failure:
   - transient provider issues retry safely (`pending` restored)
   - permanent/terminal failures go to `manual_review` with `manual_review_reason=ai_failed`.

## Statuses and meanings

### `student_answers.grading_status`
- `pending`: queued and waiting to be graded
- `processing`: currently in an active grading job attempt
- `graded`: finalized by AI
- `manual_review`: admin action required (AI failed, incomplete payload, or low confidence)
- `overridden`: admin manually overrode score/feedback

### `quiz_questions.requires_manual_review`
- `true` while theory/structured answer needs review
- `false` after AI final grade or admin override

## Retry and failure handling

`TheoryGraderService` throws `TheoryGradingException` with `retriable=true` for connection/timeout/rate-limit/5xx-like responses.

`GradeTheoryAnswerJob` behavior:
- retriable + attempts remaining: reset answer to `pending`, log `retry_scheduled`, rethrow for queue retry
- retriable exhausted: mark `manual_review` (`ai_failed`)
- permanent failures: mark `manual_review` (`ai_failed`)

A single answer failure no longer corrupts unrelated quizzes; each quiz is finalized from durable answer states.

## Auditability

`grading_attempts` records every grading/override attempt with:
- attempt number
- trigger (`ai` or `override`)
- status (`processing`, `graded`, `manual_review`, `retry_scheduled`, `overridden`)
- summary and metadata
- started/completed timestamps

Admin overrides append an `override` grading attempt that captures before/after score + status.

## Admin review workflow

Use `/admin/theory-reviews`:
- filter by status
- filter by queue state (`pending_manual_review`, `ai_failed`, `low_confidence`)
- sort oldest outstanding first

Summary cards provide:
- waiting manual review count
- grading failure count
- oldest outstanding review age

## Queue worker expectations

Run queue workers continuously in production.

Recommended:

```bash
php artisan queue:work --queue=grading,default
```

If imports are also active, split workers:

```bash
php artisan queue:work --queue=grading
php artisan queue:work --queue=imports
php artisan queue:work --queue=default
```

## Data safety notes

- Do not store API keys in DB payloads.
- `ai_result_json` and `grading_attempts.meta` store only concise error summaries and grading metadata.
- Full raw provider payloads are intentionally not persisted.
