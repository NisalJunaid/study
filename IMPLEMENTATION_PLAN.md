# IMPLEMENTATION_PLAN.md

## Purpose
This document maps the O'Level Study Help App roadmap to the **current repository baseline** and defines implementation phases that preserve existing Laravel conventions.

## Current Repository Baseline (as of 2026-03-24)

### Backend
- Fresh Laravel 10 skeleton with default providers and route files.
- `routes/web.php` serves Blade `welcome` page only.
- `routes/api.php` includes default Sanctum `/api/user` endpoint.
- `routes/channels.php` includes default `App.Models.User.{id}` channel auth.
- `app/Http/Controllers` has only base `Controller`.
- `app/Models` has only `User` model.
- `app/Policies`, `app/Services`, `app/Jobs`, `app/Events` are not yet created.
- Only core default migrations exist (`users`, `password_reset_tokens`, `failed_jobs`, `personal_access_tokens`).

### Frontend
- No Inertia setup yet.
- No React pages/components/layouts structure yet.
- `resources/js/app.js` only imports `bootstrap.js`.
- `resources/js/bootstrap.js` has axios setup and commented Echo scaffold.
- Blade `resources/views/welcome.blade.php` is the default Laravel welcome page.

### Styling / Tooling
- Vite is configured (`vite.config.js`) with `resources/css/app.css` and `resources/js/app.js` inputs.
- `resources/css/app.css` exists but is currently empty.
- Tailwind config files are not present yet.

### Packages
- Composer: Laravel 10, Sanctum, Tinker, standard dev tooling.
- NPM: Vite + laravel-vite-plugin + axios only.

## Architecture Direction (Repo-Aware)

Given current state, implement as a **modern Laravel monolith with Inertia + React** while keeping default Laravel folder conventions:

1. Keep routing server-driven in `routes/web.php` with grouped `admin` and `student` route prefixes.
2. Add thin controllers in `app/Http/Controllers/Admin` and `app/Http/Controllers/Student`.
3. Move domain logic into `app/Actions` and `app/Services` (explicit, reusable, queue-safe).
4. Add policy-based authorization for all multi-role data access.
5. Use queued jobs for theory grading and CSV processing.
6. Use events + broadcasting for progress/status updates; frontend subscriptions via Echo.
7. Use Inertia pages under `resources/js/Pages/Admin/*` and `resources/js/Pages/Student/*`.
8. Keep historical quiz integrity with question snapshots in quiz assignment records.

## Phase Plan

## Phase 0 — Foundation Alignment
**Goal:** Convert skeleton to app-ready foundation without domain features.

### Backend
- Install/configure Inertia + React bridge.
- Establish route groups and middleware structure for admin/student sections.
- Add role field strategy to `users` table and seed baseline admin/student users.
- Add shared middleware/service provider setup for auth + Inertia shared props.

### Frontend
- Create app shell/layout system for Student and Admin experiences.
- Initialize global style tokens in Tailwind.
- Add reusable primitives (`PageHeader`, `EmptyState`, `StatusBadge`, etc.).

### Infra
- Configure queue + broadcasting defaults in env docs.
- Add baseline test scaffolding for feature + policy tests.

## Phase 1 — Curriculum Core (Subjects, Topics, Questions)
**Goal:** Build admin-managed question bank and subject hierarchy.

### Data / Models
- Create `subjects`, `topics`, `questions`, `mcq_options`, `theory_question_meta` migrations and models.
- Add indices and publish-state fields.

### Admin Backend
- Form Requests + policies for CRUD operations.
- Actions for create/update/delete question workflows.
- Validation rules for MCQ/theory shape requirements.

### Admin Frontend
- Admin tables + filter bars for subjects/topics/questions.
- Question editor flow supporting both MCQ and theory modes.

## Phase 2 — Quiz Lifecycle (Student Experience)
**Goal:** End-to-end quiz creation, answering, and results (MCQ first, then mixed/theory).

### Data / Models
- Add `quizzes`, `quiz_questions`, `student_answers` tables.
- Add status fields and score fields with clear transitions.

### Domain Services
- Quiz builder service with subject/topic/mode/difficulty filters.
- MCQ grading service for immediate evaluation.
- Snapshot writer at assignment time.

### Student Frontend
- Quiz builder wizard and one-question-at-a-time quiz UI.
- Autosave interactions and progress indicators.
- Results page with score + topic breakdown.

## Phase 3 — Theory AI Grading + Manual Review
**Goal:** Safe AI-assisted grading with auditable override workflows.

### AI + Jobs
- Create `TheoryGraderService` using OpenAI Responses API schema-enforced output.
- Queue per-answer grading jobs and status transitions.
- Persist normalized result + raw AI payload.

### Admin Review
- Review queue page for low-confidence/manual-review answers.
- Override actions capturing reviewer, timestamp, and rationale.

### Safety
- Fail-closed behavior for malformed AI output.
- Student-safe feedback rendering (no rubric leakage).

## Phase 4 — CSV Import + Realtime Progress
**Goal:** Bulk ingestion with validation preview and progress updates.

### Data / Models
- Add `imports`, `import_rows` tables and status model states.

### Import Pipeline
- Upload + parse + validate job pipeline.
- Preview/confirm flow before commit.
- Chunked processing with partial-failure tolerance.

### Realtime
- Broadcast import validation/progress/completion events.
- Rehydrate from DB for durable accuracy after reconnect.

## Phase 5 — Analytics, Hardening, and UX Polish
**Goal:** Production-quality behavior, observability, and maintainable UX.

### Analytics / Auditing
- Add optional `audit_logs` for admin actions and overrides.
- Implement weak-topic metrics and progress summaries.

### Performance / Reliability
- Eager loading, pagination, caching of dashboards.
- Queue retry and dead-letter handling strategy.

### UX
- Dark mode readiness, polished empty/error/loading states.
- Final pass on admin productivity flows and keyboard navigation.

## Repository Area Responsibility Map

- `routes/`
  - `web.php`: primary app routes (Inertia + middleware groups).
  - `channels.php`: private channel authorization callbacks.

- `app/Http/Controllers/`
  - Thin request orchestration only.
  - Split into `Admin` and `Student` namespaces.

- `app/Http/Requests/`
  - Validation and authorization boundaries for each input shape.

- `app/Models/`
  - Explicit relationships and scopes (`published`, `forSubject`, etc.).

- `app/Policies/`
  - Role + ownership enforcement for admin and student actions.

- `app/Actions/`
  - Transaction-safe domain commands (build quiz, override grade, import rows).

- `app/Services/`
  - Reusable domain logic (quiz selection, AI grading, import parsing).

- `app/Jobs/`
  - Background processing (theory grading, CSV validation/import, metrics refresh).

- `app/Events/` + `app/Listeners/`
  - Realtime and asynchronous event-driven updates.

- `resources/js/Layouts/`
  - `AdminLayout`, `StudentLayout`, shared shell/navigation.

- `resources/js/Pages/Admin/` and `resources/js/Pages/Student/`
  - Route-level Inertia pages; keep pages thin and compose reusable components.

- `resources/js/Components/`
  - Reusable UI and stateful widgets.

- `database/migrations/`
  - Explicit schema evolution with indexes and constraints.

- `database/seeders/`
  - Demo accounts + realistic sample curriculum/question data.

- `tests/Feature` / `tests/Unit`
  - Feature-level behavior, policy boundaries, and isolated domain logic tests.

## Conventions for Future Tasks
- Preserve Laravel defaults and add structure incrementally (no duplicate patterns).
- Keep controllers thin; use Form Requests + policies + actions/services.
- Use explicit status enums and clear transition paths for quiz/import/grading flows.
- Ensure every async flow has persisted state + failure state + user-visible feedback.
- Broadcast for UX responsiveness, but always treat database state as source of truth.
