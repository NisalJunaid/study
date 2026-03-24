<?php

namespace App\Actions\Student;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\StudentAnswer;

class SaveQuizAnswerAction
{
    public function execute(Quiz $quiz, QuizQuestion $quizQuestion, int $studentId, array $payload): StudentAnswer
    {
        $snapshot = $quizQuestion->question_snapshot ?? [];
        $questionType = $snapshot['type'] ?? null;

        $answerAttributes = [
            'question_id' => $quizQuestion->question_id,
            'user_id' => $studentId,
            'is_correct' => null,
            'score' => null,
            'feedback' => null,
            'ai_result_json' => null,
            'graded_by' => null,
            'graded_at' => null,
        ];

        if ($questionType === 'mcq') {
            $answerAttributes['selected_option_id'] = $payload['selected_option_id'] ?? null;
            $answerAttributes['answer_text'] = null;
        } else {
            $answerAttributes['answer_text'] = isset($payload['answer_text'])
                ? trim((string) $payload['answer_text'])
                : null;
            $answerAttributes['selected_option_id'] = null;
        }

        $answerAttributes['grading_status'] = StudentAnswer::STATUS_PENDING;

        return $quiz->quizQuestions()
            ->whereKey($quizQuestion->id)
            ->firstOrFail()
            ->studentAnswer()
            ->updateOrCreate([], $answerAttributes);
    }
}
