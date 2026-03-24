<?php

namespace App\Actions\Student;

use App\Models\Quiz;

class AggregateQuizScoresAction
{
    public function execute(Quiz $quiz): float
    {
        $awardedScore = (float) $quiz->quizQuestions()->sum('awarded_score');

        $quiz->forceFill([
            'total_questions' => $quiz->quizQuestions()->count(),
            'total_possible_score' => $quiz->quizQuestions()->sum('max_score'),
            'total_awarded_score' => $awardedScore,
        ])->save();

        return $awardedScore;
    }
}
