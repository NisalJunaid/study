<?php

namespace App\Actions\Student;

use App\Models\Quiz;
use App\Models\StudentAnswer;

class FinalizeQuizGradingAction
{
    public function __construct(
        private readonly AggregateQuizScoresAction $aggregateQuizScoresAction,
    ) {
    }

    public function execute(Quiz $quiz): Quiz
    {
        $quiz->loadMissing('quizQuestions.studentAnswer');

        $pendingTheoryAnswers = $quiz->quizQuestions
            ->filter(fn ($quizQuestion) => ($quizQuestion->question_snapshot['type'] ?? null) === 'theory')
            ->pluck('studentAnswer')
            ->filter(fn ($answer) => $answer && in_array(
                $answer->grading_status,
                [StudentAnswer::STATUS_PENDING, StudentAnswer::STATUS_PROCESSING],
                true
            ))
            ->count();

        $this->aggregateQuizScoresAction->execute($quiz);

        if ($pendingTheoryAnswers > 0) {
            $quiz->forceFill([
                'status' => Quiz::STATUS_GRADING,
                'graded_at' => null,
            ])->save();

            return $quiz;
        }

        $quiz->forceFill([
            'status' => Quiz::STATUS_GRADED,
            'graded_at' => now(),
        ])->save();

        return $quiz;
    }
}
