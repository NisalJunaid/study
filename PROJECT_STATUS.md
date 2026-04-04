# Project Status (2026-04-04)

## Confirmed current implementation

- Laravel 10 monolith with role-separated student/admin route groups.
- Primary UI is Blade-rendered pages and Blade layouts.
- Inertia/React packages and some auth pages exist, but core product routes remain Blade-based.
- Queue-backed jobs are in active use for theory grading and import processing.
- Broadcast channels/events are implemented for realtime progress UX.
- Billing and subscription access rules are enforced in middleware/services.

## Implemented domains

- Student quiz setup/taking/results/history/progress.
- Admin subject/topic/question CRUD and bulk actions.
- Theory grading workflow with AI + manual review override.
- Import workflows (question CSV and curriculum JSON variants).
- Billing plans/discounts/payment review and student billing submission flow.

## Operational requirements

- Queue workers are required for grading/import completion.
- Scheduler is required for subscription enforcement and abandoned quiz cleanup.
- Broadcast infrastructure is optional for correctness but required for live event UX.

## Documentation status

- README and `.env.example` are aligned to the current code-level architecture and dependencies.
- Historical implementation plan notes should be treated as roadmap context, not as the current baseline snapshot.
