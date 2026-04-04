# Admin Content Moderation and Publish Safety

This document describes the question-quality workflow available in **Admin → Questions**.

## Moderation flags

Questions can now carry moderation flags:

- `duplicate_suspected`
- `missing_explanation`
- `invalid_options_answer_mismatch`
- `needs_review_after_import`

How flags are set:

- During create/update, the app evaluates question quality and auto-applies relevant flags.
- During imports, imported/upserted questions are flagged as `needs_review_after_import`.
- When import preview detects duplicates against existing questions, those existing questions are flagged as `duplicate_suspected` for follow-up.

## Admin filtering

In the Questions index filter bar, admins can filter by:

- Any flagged question
- A specific moderation flag

This makes it easy to batch review quality issues without leaving the current admin screen.

## Duplicate review

Use **Admin → Questions → Duplicate review** to see all questions currently flagged with `duplicate_suspected`.

Available actions:

- Open the question for manual review/edit.
- Dismiss only the duplicate flag if the content is acceptable.

> Note: the app intentionally avoids automatic destructive merges. Admins review and adjust content manually.

## Publish safety checks

Publishing is blocked when core quality checks fail.

### MCQ

Cannot publish when:

- fewer than 2 non-empty options exist, or
- there is not exactly 1 correct option.

### Theory

Cannot publish when:

- sample answer is missing, or
- marks are not greater than 0.

### Structured response

Cannot publish when:

- no structured parts exist, or
- total part marks are not greater than 0.

These checks run in both direct question edit flow and publish toggle flow, so draft/edit workflows remain safe and explicit.
