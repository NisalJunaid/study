<?php

namespace App\Actions\Student;

use App\Jobs\GradeTheoryAnswerJob;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\StudentAnswer;

class QueueTheoryGradingAction
{
    public function execute(Quiz $quiz): int
    {
        if (! $quiz->isSubmittedAttempt()) {
            return 0;
        }

        $quiz->loadMissing('quizQuestions.studentAnswer');

        $theoryAnswerIds = [];

        foreach ($quiz->quizQuestions as $quizQuestion) {
            $snapshot = $quizQuestion->question_snapshot ?? [];

            if (! in_array(($snapshot['type'] ?? null), Question::theoryLikeTypes(), true)) {
                continue;
            }

            if (! $quizQuestion->studentAnswer) {
                continue;
            }

            $quizQuestion->studentAnswer->forceFill([
                'grading_status' => StudentAnswer::STATUS_PENDING,
                'is_correct' => null,
                'score' => null,
                'feedback' => null,
                'ai_result_json' => null,
                'graded_by' => null,
                'graded_at' => null,
            ])->save();

            $theoryAnswerIds[] = $quizQuestion->studentAnswer->id;
        }

        $batchSize = max(1, (int) config('openai.batch_size', 5));

        foreach (array_chunk($theoryAnswerIds, $batchSize) as $answerIdBatch) {
            GradeTheoryAnswerJob::dispatch($quiz->id, $answerIdBatch)
                ->onQueue(config('openai.queue'))
                ->afterCommit();
        }

        return count($theoryAnswerIds);
    }
}
