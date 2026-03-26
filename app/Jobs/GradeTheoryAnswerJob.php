<?php

namespace App\Jobs;

use App\Actions\Student\FinalizeQuizGradingAction;
use App\Events\TheoryAnswerGraded;
use App\Models\Question;
use App\Models\Quiz;
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
        $quiz = Quiz::query()->find($this->quizId);
        if (! $quiz || ! $quiz->isSubmittedAttempt()) {
            return;
        }

        $answers = StudentAnswer::query()
            ->with('quizQuestion.quiz')
            ->whereIn('id', $this->studentAnswerIds)
            ->get()
            ->filter(fn (StudentAnswer $answer) => $answer->quizQuestion?->quiz_id === $this->quizId)
            ->filter(fn (StudentAnswer $answer) => in_array(($answer->quizQuestion->question_snapshot['type'] ?? null), Question::theoryLikeTypes(), true))
            ->values();

        if ($answers->isEmpty()) {
            return;
        }

        $answers->each(function (StudentAnswer $answer): void {
            $answer->forceFill([
                'grading_status' => StudentAnswer::STATUS_PROCESSING,
            ])->save();
        });

        $gradeItems = [];

        foreach ($answers as $answer) {
            $snapshot = $answer->quizQuestion->question_snapshot ?? [];
            $type = $snapshot['type'] ?? null;

            if ($type === Question::TYPE_THEORY) {
                $theoryMeta = $snapshot['theory_meta'] ?? [];
                $gradeItems[(string) $answer->id] = [
                    'question_type' => (string) $type,
                    'question' => (string) ($snapshot['question_text'] ?? ''),
                    'student_answer' => (string) ($answer->answer_text ?? ''),
                    'sample_answer' => (string) ($theoryMeta['sample_answer'] ?? ''),
                    'grading_notes' => (string) ($theoryMeta['grading_notes'] ?? ''),
                    'keywords' => is_array($theoryMeta['keywords'] ?? null) ? $theoryMeta['keywords'] : [],
                    'acceptable_phrases' => is_array($theoryMeta['acceptable_phrases'] ?? null) ? $theoryMeta['acceptable_phrases'] : [],
                    'max_score' => (float) ($theoryMeta['max_score'] ?? $answer->quizQuestion->max_score),
                    'strict_semantic' => false,
                ];
                continue;
            }

            foreach ($snapshot['structured_parts'] ?? [] as $part) {
                $partId = (string) ($part['id'] ?? '');
                if ($partId === '') {
                    continue;
                }

                $studentPartAnswer = (string) data_get($answer->answer_json ?? [], $partId, '');
                $itemKey = $answer->id.'::'.$partId;
                $gradeItems[$itemKey] = [
                    'question_type' => Question::TYPE_STRUCTURED_RESPONSE,
                    'question' => (string) ($snapshot['question_text'] ?? '')."\nPart ".($part['part_label'] ?? '').": ".($part['prompt_text'] ?? ''),
                    'student_answer' => $studentPartAnswer,
                    'sample_answer' => (string) ($part['sample_answer'] ?? ''),
                    'grading_notes' => (string) ($part['marking_notes'] ?? ''),
                    'keywords' => [],
                    'acceptable_phrases' => [],
                    'max_score' => (float) ($part['max_score'] ?? 0),
                    'strict_semantic' => true,
                ];
            }
        }

        try {
            $results = $theoryGraderService->gradeBatch($gradeItems);

            foreach ($answers as $answer) {
                $snapshot = $answer->quizQuestion->question_snapshot ?? [];
                $type = $snapshot['type'] ?? null;

                if ($type === Question::TYPE_STRUCTURED_RESPONSE) {
                    $this->persistStructuredResult($answer, $snapshot, $results);
                    TheoryAnswerGraded::dispatch($answer->id);
                    continue;
                }

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

        $loadedQuiz = $answers->first()?->quizQuestion?->quiz;

        if ($loadedQuiz) {
            $finalizeQuizGradingAction->execute($loadedQuiz);
        }
    }

    private function persistStructuredResult(StudentAnswer $answer, array $snapshot, array $results): void
    {
        $parts = collect($snapshot['structured_parts'] ?? []);
        $partGrades = [];
        $total = 0.0;
        $flagManualReview = false;
        $hasMissingPartResult = false;

        foreach ($parts as $part) {
            $partId = (string) ($part['id'] ?? '');
            if ($partId === '') {
                continue;
            }

            $result = $results[$answer->id.'::'.$partId] ?? null;

            if (! $result instanceof TheoryGradeResult) {
                $hasMissingPartResult = true;
                continue;
            }

            $total += $result->score;
            $flagManualReview = $flagManualReview || $result->shouldFlagForReview;
            $partGrades[$partId] = [
                'part_label' => $part['part_label'] ?? '',
                'score' => $result->score,
                'max_score' => (float) ($part['max_score'] ?? 0),
                'feedback' => $result->feedback,
                'verdict' => $result->verdict,
                'confidence' => $result->confidence,
                'routing' => [
                    'model' => data_get($result->raw, 'routing.model'),
                    'tier' => data_get($result->raw, 'routing.tier'),
                    'profile' => data_get($result->raw, 'routing.profile'),
                    'escalated' => (bool) data_get($result->raw, 'escalated', false),
                ],
            ];
        }

        if ($hasMissingPartResult) {
            $this->markManualReview(
                $answer,
                'One or more structured parts could not be graded automatically; manual review is required.',
                ['error' => 'Missing structured subpart result.']
            );

            return;
        }

        DB::transaction(function () use ($answer, $partGrades, $total, $flagManualReview): void {
            $maxScore = (float) $answer->quizQuestion->max_score;
            $boundedScore = max(0, min($total, $maxScore));
            $isCorrect = $boundedScore >= $maxScore && $maxScore > 0;

            $answer->forceFill([
                'is_correct' => $isCorrect,
                'score' => $boundedScore,
                'feedback' => 'Structured response graded part-by-part.',
                'grading_status' => $flagManualReview ? StudentAnswer::STATUS_MANUAL_REVIEW : StudentAnswer::STATUS_GRADED,
                'ai_result_json' => [
                    'kind' => 'structured_response',
                    'parts' => $partGrades,
                ],
                'graded_at' => now(),
            ])->save();

            $answer->quizQuestion->forceFill([
                'awarded_score' => $boundedScore,
                'is_correct' => $isCorrect,
                'requires_manual_review' => $flagManualReview,
            ])->save();
        });
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
