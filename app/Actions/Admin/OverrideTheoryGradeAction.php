<?php

namespace App\Actions\Admin;

use App\Actions\Student\FinalizeQuizGradingAction;
use App\Models\StudentAnswer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OverrideTheoryGradeAction
{
    public function __construct(
        private readonly FinalizeQuizGradingAction $finalizeQuizGradingAction,
    ) {
    }

    public function execute(StudentAnswer $answer, array $payload, User $admin): StudentAnswer
    {
        DB::transaction(function () use ($answer, $payload, $admin): void {
            $score = min((float) $answer->quizQuestion->max_score, max(0, (float) $payload['score']));
            $feedback = trim((string) ($payload['feedback'] ?? ''));

            $answer->forceFill([
                'score' => $score,
                'feedback' => $feedback,
                'grading_status' => StudentAnswer::STATUS_OVERRIDDEN,
                'is_correct' => $score > 0,
                'graded_by' => $admin->id,
                'graded_at' => now(),
            ])->save();

            $answer->quizQuestion->forceFill([
                'awarded_score' => $score,
                'is_correct' => $score > 0,
                'requires_manual_review' => false,
            ])->save();
        });

        $this->finalizeQuizGradingAction->execute($answer->quizQuestion->quiz);

        return $answer->fresh(['quizQuestion.quiz', 'user', 'question']);
    }
}
