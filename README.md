# O'Level Study Help App

A Laravel 10 study platform for O'Level students with separate student/admin experiences, quiz generation, CSV question import, queue-driven theory grading, and realtime progress updates.

## Current Tech Stack

- Laravel 10 (PHP 8.2+)
- Blade-based UI (student + admin pages)
- MySQL / MariaDB
- Redis (recommended for queues + broadcast driver)
- Broadcasting over Pusher protocol (compatible with Laravel Reverb)
- OpenAI Responses API for theory answer grading

## Student Flow

Levels → Subjects → Topics (optional) → Quiz Builder → Quiz Taking → Results

## Implemented Features

### Student
- Subject browsing with topic/question availability counters
- Quiz builder by subject, optional topics, mode (MCQ/Theory/Mixed), difficulty, and count
- Quiz taking with autosave (AJAX)
- MCQ grading on submit
- Theory grading queued in background jobs
- Results page with realtime grading progress and per-answer feedback
- History and progress analytics views

### Admin
- Subject CRUD
- Topic CRUD
- Question CRUD (MCQ + theory rubric metadata, publish/unpublish)
- CSV question import with validation preview and confirmation step
- Import row outcomes and progress metrics
- Theory grading review queue with manual override

### Realtime Events
- Quiz grading progress updates
- Theory answer graded updates
- Import progress updates
- Question bank change notifications

## Quick Start

1. Install dependencies:

```bash
composer install
npm install
```

2. Create environment file and app key:

```bash
cp .env.example .env
php artisan key:generate
```

3. Configure database + services in `.env` (see **Required Environment Keys** below).

4. Run migrations and seed demo data:

```bash
php artisan migrate --seed
```

5. Build frontend assets:

```bash
npm run build
# or: npm run dev (during development)
```

6. Start app + background workers:

```bash
php artisan serve
php artisan queue:work --queue=default
```

If you separate queues, run workers per queue (recommended in production), e.g.:

```bash
php artisan queue:work --queue=imports
php artisan queue:work --queue=grading
```

## Required Environment Keys

The app depends on OpenAI + broadcasting + queues for production workflows.

### OpenAI / Theory Grading

```env
OPENAI_API_KEY=
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_GRADING_MODEL=gpt-4.1-mini
OPENAI_TIMEOUT_SECONDS=30
OPENAI_CONFIDENCE_MANUAL_REVIEW_THRESHOLD=0.55
OPENAI_GRADING_QUEUE=default
```

### Broadcasting / Realtime

```env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

> Laravel Reverb can be used by pointing these Pusher-compatible values to your Reverb host/port.

### Queueing

```env
QUEUE_CONNECTION=redis
OPENAI_GRADING_QUEUE=default
IMPORT_PROCESSING_QUEUE=default
```

`QUEUE_CONNECTION=sync` works for local smoke testing, but production should use queued workers.

## Queue Usage Notes

- `GradeTheoryAnswerJob` handles theory grading asynchronously.
- `ProcessQuestionImportJob` handles row-by-row CSV import asynchronously.
- Both jobs emit broadcast events used by admin/student realtime UI.
- If workers are down, theory grading/import progress will stall and remain user-visible as pending/manual-review statuses.

> ⚠️ Queue workers are mandatory in production for theory grading and CSV imports.
> ℹ️ Broadcasting is optional for correctness. If realtime delivery is unavailable, quiz submission still succeeds and results can refresh via HTTP polling fallback.

## Demo Accounts (Seeded)

When you run `php artisan migrate --seed`, `StudyHelpDemoSeeder` creates:

- Admin: `admin@olevel.test` / `password`
- Student: `student@olevel.test` / `password`

Additional student records are seeded for local testing.

## Production Readiness Checklist

Before deployment:

- Use `APP_ENV=production`, `APP_DEBUG=false`.
- Configure Redis + queue workers (Supervisor/systemd).
- Configure broadcasting credentials/host and ensure websocket endpoint is reachable.
- Set `OPENAI_API_KEY` and verify outbound connectivity.
- Run `php artisan storage:link` if question images/uploads are used.
- Run `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`.
- Monitor `failed_jobs` and `storage/logs/laravel.log`.

## Tests

Run full test suite:

```bash
php artisan test
```

## Notes

- This repository currently uses Blade templates (not yet Inertia/React pages).
- AI grading is assistive: failed/low-confidence responses are directed into manual review workflows.
