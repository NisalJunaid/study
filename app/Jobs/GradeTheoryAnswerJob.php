<?php

namespace App\Jobs;

use App\Actions\Student\FinalizeQuizGradingAction;
use App\Events\TheoryAnswerGraded;
use App\Models\StudentAnswer;
use App\Services\AI\TheoryGraderService;
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

    public int $tries = 2;

    public function __construct(
        public readonly int $studentAnswerId,
    ) {
    }

    public function handle(TheoryGraderService $theoryGraderService, FinalizeQuizGradingAction $finalizeQuizGradingAction): void
    {
        $answer = StudentAnswer::query()
            ->with('quizQuestion.quiz')
            ->find($this->studentAnswerId);

        if (! $answer || ! $answer->quizQuestion || ! $answer->quizQuestion->quiz) {
            return;
        }

        $snapshot = $answer->quizQuestion->question_snapshot ?? [];
        if (($snapshot['type'] ?? null) !== 'theory') {
            return;
        }

        $answer->forceFill([
            'grading_status' => StudentAnswer::STATUS_PROCESSING,
        ])->save();

        try {
            $theoryMeta = $snapshot['theory_meta'] ?? [];

            $result = $theoryGraderService->grade([
                'question' => (string) ($snapshot['question_text'] ?? ''),
                'student_answer' => (string) ($answer->answer_text ?? ''),
                'sample_answer' => (string) ($theoryMeta['sample_answer'] ?? ''),
                'grading_notes' => (string) ($theoryMeta['grading_notes'] ?? ''),
                'keywords' => is_array($theoryMeta['keywords'] ?? null) ? $theoryMeta['keywords'] : [],
                'acceptable_phrases' => is_array($theoryMeta['acceptable_phrases'] ?? null) ? $theoryMeta['acceptable_phrases'] : [],
                'max_score' => (float) ($theoryMeta['max_score'] ?? $answer->quizQuestion->max_score),
            ]);

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
        } catch (Throwable $exception) {
            $answer->forceFill([
                'grading_status' => StudentAnswer::STATUS_MANUAL_REVIEW,
                'feedback' => 'Automatic grading failed; this answer requires manual review.',
                'ai_result_json' => [
                    'error' => $exception->getMessage(),
                    'failed_at' => now()->toIso8601String(),
                ],
                'graded_at' => now(),
            ])->save();

            $answer->quizQuestion->forceFill([
                'requires_manual_review' => true,
                'awarded_score' => null,
                'is_correct' => null,
            ])->save();
        }

        TheoryAnswerGraded::dispatch($answer->id);
        $finalizeQuizGradingAction->execute($answer->quizQuestion->quiz);
    }
}
