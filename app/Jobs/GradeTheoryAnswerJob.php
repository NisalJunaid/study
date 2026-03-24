<?php

namespace App\Jobs;

use App\Actions\Student\FinalizeQuizGradingAction;
use App\Events\TheoryAnswerGraded;
use App\Models\StudentAnswer;
use App\Services\AI\TheoryGraderService;
use App\Support\DTOs\TheoryGradeResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class GradeTheoryAnswerJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /**
     * @param  array<int>  $studentAnswerIds
     */
    public function __construct(
        public readonly int $quizId,
        public readonly array $studentAnswerIds,
    ) {
        $this->tries = max(1, (int) config('openai.retry_count', 2));
    }

    public function handle(TheoryGraderService $theoryGraderService, FinalizeQuizGradingAction $finalizeQuizGradingAction): void
    {
        $answers = StudentAnswer::query()
            ->with('quizQuestion.quiz')
            ->whereIn('id', $this->studentAnswerIds)
            ->get()
            ->filter(fn (StudentAnswer $answer) => $answer->quizQuestion?->quiz_id === $this->quizId)
            ->filter(fn (StudentAnswer $answer) => ($answer->quizQuestion->question_snapshot['type'] ?? null) === 'theory')
            ->values();

        if ($answers->isEmpty()) {
            return;
        }

        $answers->each(function (StudentAnswer $answer): void {
            $answer->forceFill([
                'grading_status' => StudentAnswer::STATUS_PROCESSING,
            ])->save();
        });

        $gradeItems = $answers->mapWithKeys(function (StudentAnswer $answer): array {
            $snapshot = $answer->quizQuestion->question_snapshot ?? [];
            $theoryMeta = $snapshot['theory_meta'] ?? [];

            return [
                (string) $answer->id => [
                    'question' => (string) ($snapshot['question_text'] ?? ''),
                    'student_answer' => (string) ($answer->answer_text ?? ''),
                    'sample_answer' => (string) ($theoryMeta['sample_answer'] ?? ''),
                    'grading_notes' => (string) ($theoryMeta['grading_notes'] ?? ''),
                    'keywords' => is_array($theoryMeta['keywords'] ?? null) ? $theoryMeta['keywords'] : [],
                    'acceptable_phrases' => is_array($theoryMeta['acceptable_phrases'] ?? null) ? $theoryMeta['acceptable_phrases'] : [],
                    'max_score' => (float) ($theoryMeta['max_score'] ?? $answer->quizQuestion->max_score),
                ],
            ];
        })->all();

        try {
            $results = $theoryGraderService->gradeBatch($gradeItems);

            foreach ($answers as $answer) {
                $result = $results[(string) $answer->id] ?? null;

                if (! $result instanceof TheoryGradeResult) {
                    $this->markManualReview(
                        $answer,
                        'Automatic grading returned an incomplete batch result; this answer requires manual review.',
                        ['error' => 'Missing item from graded batch.']
                    );

                    TheoryAnswerGraded::dispatch($answer->id);

                    continue;
                }

                $this->persistGradedResult($answer, $result);
                TheoryAnswerGraded::dispatch($answer->id);
            }
        } catch (Throwable $exception) {
            foreach ($answers as $answer) {
                $this->markManualReview(
                    $answer,
                    'Automatic grading failed; this answer requires manual review.',
                    [
                        'error' => $exception->getMessage(),
                        'failed_at' => now()->toIso8601String(),
                    ]
                );

                TheoryAnswerGraded::dispatch($answer->id);
            }
        }

        $quiz = $answers->first()?->quizQuestion?->quiz;

        if ($quiz) {
            $finalizeQuizGradingAction->execute($quiz);
        }
    }

    private function persistGradedResult(StudentAnswer $answer, TheoryGradeResult $result): void
    {
        DB::transaction(function () use ($answer, $result): void {
            $isCorrect = $result->verdict === 'correct';
            $manualReview = $result->shouldFlagForReview;

            $answer->forceFill([
                'is_correct' => $isCorrect,
                'score' => $result->score,
                'feedback' => $result->feedback,
                'grading_status' => $manualReview ? StudentAnswer::STATUS_MANUAL_REVIEW : StudentAnswer::STATUS_GRADED,
                'ai_result_json' => $result->raw,
                'graded_at' => now(),
            ])->save();

            $answer->quizQuestion->forceFill([
                'awarded_score' => $result->score,
                'is_correct' => $isCorrect,
                'requires_manual_review' => $manualReview,
            ])->save();
        });
    }

    private function markManualReview(StudentAnswer $answer, string $feedback, array $meta): void
    {
        DB::transaction(function () use ($answer, $feedback, $meta): void {
            $answer->forceFill([
                'grading_status' => StudentAnswer::STATUS_MANUAL_REVIEW,
                'feedback' => $feedback,
                'ai_result_json' => $meta,
                'graded_at' => now(),
                'is_correct' => null,
                'score' => null,
            ])->save();

            $answer->quizQuestion->forceFill([
                'requires_manual_review' => true,
                'awarded_score' => null,
                'is_correct' => null,
            ])->save();
        });
    }
}
