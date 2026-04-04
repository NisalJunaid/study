# Admin Notes: Quiz Presets

Student quiz presets are implemented as configuration helpers over the existing quiz setup engine.

## What admins should know

- Presets are UI-level prefills for `/quiz/setup`; they are not a second quiz generator.
- Validation and generation still run through:
  - `App\Http\Requests\Student\StoreQuizRequest`
  - `App\Actions\Student\BuildQuizAction`
- Weak-topic and recommendation presets are backed by real analytics from `StudentProgressAnalyticsService`.
- Fallbacks are explicit and student-visible when there is insufficient history or insufficient inventory.

## Operational implication

Preset quality depends on:

- published question inventory by subject/topic/mode
- student grading history quality
- topic tagging consistency in the question bank

Maintaining strong topic metadata and published coverage directly improves weak-topic preset accuracy.
