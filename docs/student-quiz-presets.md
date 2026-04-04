# Student Quiz Presets

Quiz presets are shortcuts on the existing **/quiz/setup** page. They only prefill the same setup fields that are already used by the normal quiz builder pipeline.

## Supported presets

- **Quick Revision**
  - Prefills mixed mode with a short question count.
- **Weak Topics Only**
  - Uses real weak-topic analytics from the student's graded history.
  - Prefills subject/topic filters plus mixed mode.
  - If the student has no weak-topic history yet, it falls back to mixed practice and shows an explanation.
- **Mixed Practice**
  - Prefills a balanced mixed setup for broader revision.
- **Exam Style / Full Practice**
  - Prefills a longer mixed setup intended for full-practice stamina.
- **Continue Recommended Practice**
  - Reuses the same recommendation payload used on the progress dashboard.

## Important behavior

- Presets **do not create quizzes directly**.
- Students can still edit levels, subjects, topics, mode, difficulty, and count before submitting.
- Quiz creation still goes through the existing `StoreQuizRequest` validation and `BuildQuizAction` generation flow.
- If preset filters have no matching inventory, topic filters are relaxed and count may be reduced with a visible notice.
