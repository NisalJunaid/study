<?php

namespace App\Services\Admin;

use App\Models\Question;

class QuestionModerationService
{
    public function evaluateFlags(Question $question, bool $needsReviewAfterImport = false): array
    {
        $flags = [];

        if (trim((string) $question->explanation) === '') {
            $flags[] = Question::FLAG_MISSING_EXPLANATION;
        }

        if ($question->type === Question::TYPE_MCQ) {
            $options = $question->relationLoaded('mcqOptions') ? $question->mcqOptions : $question->mcqOptions()->get();
            $nonEmptyOptionCount = $options->filter(fn ($option) => trim((string) $option->option_text) !== '')->count();
            $correctCount = $options->where('is_correct', true)->count();

            if ($nonEmptyOptionCount < 2 || $correctCount !== 1) {
                $flags[] = Question::FLAG_INVALID_OPTIONS_ANSWER_MISMATCH;
            }
        }

        if ($this->hasPotentialDuplicate($question)) {
            $flags[] = Question::FLAG_DUPLICATE_SUSPECTED;
        }

        if ($needsReviewAfterImport) {
            $flags[] = Question::FLAG_NEEDS_REVIEW_AFTER_IMPORT;
        }

        return collect($flags)->unique()->values()->all();
    }

    public function flagLabels(): array
    {
        return [
            Question::FLAG_DUPLICATE_SUSPECTED => 'Duplicate suspected',
            Question::FLAG_MISSING_EXPLANATION => 'Missing explanation',
            Question::FLAG_INVALID_OPTIONS_ANSWER_MISMATCH => 'Invalid options / answer mismatch',
            Question::FLAG_NEEDS_REVIEW_AFTER_IMPORT => 'Needs review after import',
        ];
    }

    private function hasPotentialDuplicate(Question $question): bool
    {
        $normalized = $this->normalizeQuestionText((string) $question->question_text);

        if ($normalized === '') {
            return false;
        }

        return Question::query()
            ->where('id', '!=', $question->id)
            ->where('subject_id', $question->subject_id)
            ->where('type', $question->type)
            ->when($question->topic_id, fn ($query) => $query->where('topic_id', $question->topic_id), fn ($query) => $query->whereNull('topic_id'))
            ->get(['question_text'])
            ->contains(function (Question $candidate) use ($normalized): bool {
                return $this->normalizeQuestionText((string) $candidate->question_text) === $normalized;
            });
    }

    private function normalizeQuestionText(string $questionText): string
    {
        $normalizedWhitespace = preg_replace('/\s+/', ' ', trim($questionText));

        return strtolower((string) $normalizedWhitespace);
    }
}
