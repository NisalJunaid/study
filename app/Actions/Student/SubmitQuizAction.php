<?php

namespace App\Actions\Student;

use App\Events\QuizGradingProgressUpdated;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Services\Billing\QuizAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SubmitQuizAction
{
    public function __construct(
        private readonly GradeMcqQuizAction $gradeMcqQuizAction,
        private readonly AggregateQuizScoresAction $aggregateQuizScoresAction,
        private readonly QueueTheoryGradingAction $queueTheoryGradingAction,
        private readonly QuizAccessService $quizAccessService,
    ) {
    }

    public function execute(Quiz $quiz, int $studentId): array
    {
        if ($quiz->user_id !== $studentId) {
            throw new RuntimeException('You are not allowed to submit this quiz.');
        }

        return DB::transaction(function () use ($quiz) {
            $quiz = Quiz::query()
                ->whereKey($quiz->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($quiz->isSubmittedAttempt()) {
                return [
                    'quiz' => $quiz,
                    'message' => 'This quiz has already been submitted.',
                ];
            }

            if (! $quiz->canTransitionTo(Quiz::STATUS_SUBMITTED)) {
                throw new RuntimeException('This quiz cannot be submitted in its current state.');
            }

            $quiz->load([
                'quizQuestions' => fn ($query) => $query->orderBy('order_no')->with('studentAnswer'),
            ]);

            $this->ensureAnswerRecordsExist($quiz);
            $this->gradeMcqQuizAction->execute($quiz);

            $hasTheoryQuestions = false;

            foreach ($quiz->quizQuestions as $quizQuestion) {
                $snapshot = $quizQuestion->question_snapshot ?? [];
                if (! in_array(($snapshot['type'] ?? null), Question::theoryLikeTypes(), true)) {
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

            $submittedAt = now();

            $quiz->transitionTo(Quiz::STATUS_SUBMITTED, [
                'last_interacted_at' => now(),
                'submitted_at' => $submittedAt,
                'graded_at' => null,
            ]);

            if ($hasTheoryQuestions) {
                $quiz->transitionTo(Quiz::STATUS_GRADING);
            } else {
                $quiz->transitionTo(Quiz::STATUS_GRADED, [
                    'graded_at' => now(),
                ]);
            }

            $this->aggregateQuizScoresAction->execute($quiz);
            $this->quizAccessService->registerSubmittedQuizUsage($quiz->user, $quiz);

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
