<?php

namespace App\Actions\Student;

use App\Jobs\GradeTheoryAnswerJob;
use App\Models\Quiz;
use App\Models\StudentAnswer;

class QueueTheoryGradingAction
{
    public function execute(Quiz $quiz): int
    {
        $quiz->loadMissing('quizQuestions.studentAnswer');

        $dispatched = 0;

        foreach ($quiz->quizQuestions as $quizQuestion) {
            $snapshot = $quizQuestion->question_snapshot ?? [];

            if (($snapshot['type'] ?? null) !== 'theory') {
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

            GradeTheoryAnswerJob::dispatch($quizQuestion->studentAnswer->id)
                ->onQueue(config('openai.queue'))
                ->afterCommit();

            $dispatched++;
        }

        return $dispatched;
    }
}
