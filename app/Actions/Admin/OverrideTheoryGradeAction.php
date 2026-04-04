<?php

namespace App\Actions\Admin;

use App\Actions\Student\FinalizeQuizGradingAction;
use App\Models\GradingAttempt;
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

            $previous = [
                'score' => $answer->score,
                'feedback' => $answer->feedback,
                'grading_status' => $answer->grading_status,
                'graded_by' => $answer->graded_by,
                'graded_at' => optional($answer->graded_at)?->toIso8601String(),
            ];

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

            $nextAttempt = (int) GradingAttempt::query()
                ->where('student_answer_id', $answer->id)
                ->max('attempt_number') + 1;

            GradingAttempt::query()->create([
                'student_answer_id' => $answer->id,
                'quiz_id' => $answer->quizQuestion->quiz_id,
                'actor_id' => $admin->id,
                'attempt_number' => $nextAttempt,
                'trigger' => 'override',
                'status' => 'overridden',
                'summary' => 'Admin override applied.',
                'meta' => [
                    'before' => $previous,
                    'after' => [
                        'score' => $score,
                        'feedback' => $feedback,
                        'grading_status' => StudentAnswer::STATUS_OVERRIDDEN,
                    ],
                ],
                'started_at' => now(),
                'completed_at' => now(),
            ]);
        });

        $this->finalizeQuizGradingAction->execute($answer->quizQuestion->quiz);

        return $answer->fresh(['quizQuestion.quiz', 'user', 'question']);
    }
}
