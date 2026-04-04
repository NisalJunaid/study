# IMPLEMENTATION_PLAN.md

## Purpose
Living roadmap aligned to the **actual current codebase**.

## Current Baseline (verified 2026-04-04)

- Core application uses Laravel 10 + Blade-first pages for student/admin product flows.
- Inertia/React assets/packages are present but not yet the dominant app shell for core workflows.
- Domain logic is distributed across Actions, Services, Jobs, Policies, middleware, and Form Requests.
- Queue and broadcast integrations are already part of quiz grading/import workflows.
- Billing/subscription and access gating are integrated into quiz entry and suspension handling.

## Active architecture direction

1. Preserve the existing Laravel monolith and route conventions.
2. Keep controllers thin and continue using Requests/Policies/Actions/Services.
3. Keep async flows durable-first (DB truth), with broadcast as UX enhancement.
4. Harden operations (queue/scheduler/retry/monitoring) before major frontend migration.

## Priority roadmap

### Phase A — Production hardening
- Keep docs/env/testing aligned to real implementation.
- Ensure queue/scheduler dependencies are explicit and monitored.
- Expand smoke-test coverage for access control, billing gating, and quiz lifecycle.

### Phase B — Feature consolidation
- Continue admin and student workflow refinements within current Blade architecture.
- Improve import observability and failure reporting.
- Improve grading/manual-review operator tooling.

### Phase C — Optional UI migration path
- If adopted, migrate route-by-route to Inertia/React without breaking existing URLs.
- Keep backward-compatible route contracts and policy checks.
- Avoid dual architecture drift by deprecating replaced Blade pages incrementally.
