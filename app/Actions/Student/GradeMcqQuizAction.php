<?php

namespace App\Actions\Student;

use App\Models\Quiz;
use App\Models\StudentAnswer;
use Illuminate\Support\Collection;

class GradeMcqQuizAction
{
    public function execute(Quiz $quiz): Collection
    {
        $gradedRows = collect();

        $quiz->loadMissing('quizQuestions.studentAnswer');

        foreach ($quiz->quizQuestions as $quizQuestion) {
            $snapshot = $quizQuestion->question_snapshot ?? [];

            if (($snapshot['type'] ?? null) !== 'mcq') {
                continue;
            }

            $correctOptionId = collect($snapshot['options'] ?? [])
                ->firstWhere('is_correct', true)['id']
                ?? data_get($snapshot, 'correct_option_id');

            if (! $correctOptionId) {
                $correctOptionId = $this->resolveCorrectOptionIdFromDatabase($quizQuestion->question_id);
            }

            $answer = $quizQuestion->studentAnswer;
            if (! $answer) {
                $answer = $quizQuestion->studentAnswer()->create([
                    'question_id' => $quizQuestion->question_id,
                    'user_id' => $quiz->user_id,
                    'grading_status' => StudentAnswer::STATUS_PENDING,
                ]);
            }

            $selectedOptionId = $answer->selected_option_id;
            $isCorrect = $selectedOptionId !== null && (int) $selectedOptionId === (int) $correctOptionId;
            $score = $isCorrect ? (float) $quizQuestion->max_score : 0.0;

            $answer->forceFill([
                'is_correct' => $isCorrect,
                'score' => $score,
                'grading_status' => StudentAnswer::STATUS_GRADED,
                'graded_at' => now(),
            ])->save();

            $quizQuestion->forceFill([
                'is_correct' => $isCorrect,
                'awarded_score' => $score,
                'requires_manual_review' => false,
            ])->save();

            $gradedRows->push($quizQuestion);
        }

        return $gradedRows;
    }

    private function resolveCorrectOptionIdFromDatabase(int $questionId): ?int
    {
        return \App\Models\McqOption::query()
            ->where('question_id', $questionId)
            ->where('is_correct', true)
            ->value('id');
    }
}
