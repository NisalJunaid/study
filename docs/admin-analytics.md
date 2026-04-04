# Admin Analytics Dashboard

The admin dashboard now includes **operational analytics** intended for daily platform operations (not vanity charts).

## Operational metrics

- **Quizzes started**: count of quizzes currently in `in_progress`.
  - Action: high growth can indicate strong engagement, but compare with submissions to spot drop-off.
- **Quizzes submitted**: total quizzes in `submitted`, `grading`, or `graded` states.
  - Action: if this grows faster than graded count, grading throughput may be behind.
- **Quizzes graded**: quizzes completed with final grading (`graded`).
  - Action: use with pending grading to monitor queue health.
- **Pending grading**: student answers with grading status `pending` or `processing`.
  - Action: inspect queue workers, provider health, and failed jobs when this backlog climbs.
- **Pending manual review**: student answers flagged as `manual_review`.
  - Action: triage in `/admin/theory-reviews` and prioritize oldest first.
- **Oldest review age**: how long the oldest manual review item has been waiting.
  - Action: treat this as SLA signal for reviewer throughput.
- **Active students (14d)**: distinct student accounts with recent quiz activity in the past 14 days.
  - Action: monitor cohort engagement changes after curriculum or grading updates.
- **Subject performance**: average score percentage and attempt volume by subject.
  - Action: identify where instructional quality or question balance may need tuning.
- **Common weak areas**: low-performing topics (minimum 3 attempts) across students.
  - Action: prioritize content fixes, additional practice sets, and rubric checks in these topics.

## Content health metrics

- **Published questions** vs **Unpublished questions**: current inventory readiness.
  - Action: investigate bottlenecks if unpublished stock remains high.
- **Flagged questions**: questions carrying moderation flags.
  - Action: review in admin question workflows and clear resolved flags.
- **Recently imported (7d)**: distinct questions created from imports in the last 7 days.
  - Action: verify import quality after bulk uploads.
- **Duplicate suspected**: count of questions marked with duplicate-suspected flag.
  - Action: use Duplicate Review to merge/clean up similar questions.

## Performance and implementation notes

- Metrics are built from aggregate SQL queries over existing quiz, answer, question, and import tables.
- No heavy charting dependency is required.
- Dashboard is admin-only and protected by existing `role:admin` middleware.
