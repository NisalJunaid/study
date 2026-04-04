<?php

namespace App\Actions\Student;

use App\Events\QuizGradingProgressUpdated;
use App\Models\Quiz;
use App\Models\Question;
use App\Models\StudentAnswer;
use Illuminate\Support\Facades\DB;

class FinalizeQuizGradingAction
{
    public function __construct(
        private readonly AggregateQuizScoresAction $aggregateQuizScoresAction,
    ) {
    }

    public function execute(Quiz $quiz): Quiz
    {
        return DB::transaction(function () use ($quiz): Quiz {
            $quiz = Quiz::query()
                ->whereKey($quiz->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($quiz->status, [Quiz::STATUS_SUBMITTED, Quiz::STATUS_GRADING, Quiz::STATUS_GRADED], true)) {
                return $quiz;
            }

            $quiz->load([
                'quizQuestions' => fn ($query) => $query->with('studentAnswer'),
            ]);

            $pendingTheoryAnswers = $quiz->quizQuestions
                ->filter(fn ($quizQuestion) => in_array(($quizQuestion->question_snapshot['type'] ?? null), Question::theoryLikeTypes(), true))
                ->pluck('studentAnswer')
                ->filter(fn ($answer) => $answer && in_array(
                    $answer->grading_status,
                    [StudentAnswer::STATUS_PENDING, StudentAnswer::STATUS_PROCESSING],
                    true
                ))
                ->count();

            $this->aggregateQuizScoresAction->execute($quiz);

            if ($pendingTheoryAnswers > 0) {
                $quiz->transitionTo(Quiz::STATUS_GRADING, [
                    'graded_at' => null,
                ]);
                QuizGradingProgressUpdated::dispatch($quiz->id);

                return $quiz;
            }

            $quiz->transitionTo(Quiz::STATUS_GRADED, [
                'graded_at' => now(),
            ]);
            QuizGradingProgressUpdated::dispatch($quiz->id);

            return $quiz;
        });
    }
}
