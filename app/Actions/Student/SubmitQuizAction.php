<?php

namespace App\Actions\Student;

use App\Events\QuizGradingProgressUpdated;
use App\Models\Quiz;
use App\Models\StudentAnswer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SubmitQuizAction
{
    public function __construct(
        private readonly GradeMcqQuizAction $gradeMcqQuizAction,
        private readonly AggregateQuizScoresAction $aggregateQuizScoresAction,
        private readonly QueueTheoryGradingAction $queueTheoryGradingAction,
    ) {
    }

    public function execute(Quiz $quiz, int $studentId): array
    {
        if ($quiz->user_id !== $studentId) {
            throw new RuntimeException('You are not allowed to submit this quiz.');
        }

        if (in_array($quiz->status, [Quiz::STATUS_SUBMITTED, Quiz::STATUS_GRADING, Quiz::STATUS_GRADED], true)) {
            return [
                'quiz' => $quiz,
                'message' => 'This quiz has already been submitted.',
            ];
        }

        return DB::transaction(function () use ($quiz) {
            $quiz->load([
                'quizQuestions' => fn ($query) => $query->orderBy('order_no')->with('studentAnswer'),
            ]);

            $this->ensureAnswerRecordsExist($quiz);
            $this->gradeMcqQuizAction->execute($quiz);

            $hasTheoryQuestions = false;

            foreach ($quiz->quizQuestions as $quizQuestion) {
                $snapshot = $quizQuestion->question_snapshot ?? [];
                if (($snapshot['type'] ?? null) !== 'theory') {
                    continue;
                }

                $hasTheoryQuestions = true;
                $answer = $quizQuestion->studentAnswer;

                $answer?->forceFill([
                    'grading_status' => StudentAnswer::STATUS_PENDING,
                    'is_correct' => null,
                    'score' => null,
                    'feedback' => null,
                    'ai_result_json' => null,
                    'graded_by' => null,
                    'graded_at' => null,
                ])->save();

                $quizQuestion->forceFill([
                    'awarded_score' => null,
                    'is_correct' => null,
                    'requires_manual_review' => true,
                ])->save();
            }

            $quiz->forceFill([
                'status' => $hasTheoryQuestions ? Quiz::STATUS_GRADING : Quiz::STATUS_GRADED,
                'submitted_at' => now(),
                'graded_at' => $hasTheoryQuestions ? null : now(),
            ])->save();

            $this->aggregateQuizScoresAction->execute($quiz);

            $queuedCount = $hasTheoryQuestions
                ? $this->queueTheoryGradingAction->execute($quiz)
                : 0;

            try {
                QuizGradingProgressUpdated::dispatch($quiz->id);
            } catch (\Throwable $e) {
                Log::warning('Broadcast failed but quiz submission continues', [
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'quiz' => $quiz,
                'message' => $hasTheoryQuestions
                    ? "Quiz submitted. MCQ graded now; {$queuedCount} theory answer(s) queued for grading."
                    : 'Quiz submitted and graded successfully.',
            ];
        });
    }

    private function ensureAnswerRecordsExist(Quiz $quiz): void
    {
        foreach ($quiz->quizQuestions as $quizQuestion) {
            if ($quizQuestion->studentAnswer) {
                continue;
            }

            $quizQuestion->studentAnswer()->create([
                'question_id' => $quizQuestion->question_id,
                'user_id' => $quiz->user_id,
                'grading_status' => StudentAnswer::STATUS_PENDING,
            ]);
        }
    }
}
