<?php

namespace App\Actions\Student;

use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\StudentAnswer;
use RuntimeException;

class SaveQuizAnswerAction
{
    public function execute(Quiz $quiz, QuizQuestion $quizQuestion, int $studentId, array $payload): StudentAnswer
    {
        $snapshot = $quizQuestion->question_snapshot ?? [];
        $questionType = $snapshot['type'] ?? null;

        $existingAnswer = $quizQuestion->studentAnswer;

        if ($existingAnswer && $existingAnswer->answered_at !== null) {
            throw new RuntimeException('This question is already locked and cannot be edited.');
        }

        $answerAttributes = [
            'question_id' => $quizQuestion->question_id,
            'user_id' => $studentId,
            'is_correct' => null,
            'score' => null,
            'feedback' => null,
            'ai_result_json' => null,
            'graded_by' => null,
            'graded_at' => null,
            'question_started_at' => $payload['question_started_at'] ?? null,
            'answered_at' => $payload['answered_at'] ?? null,
            'ideal_time_seconds' => $payload['ideal_time_seconds'] ?? null,
            'answer_duration_seconds' => $payload['answer_duration_seconds'] ?? null,
            'answered_on_time' => $payload['answered_on_time'] ?? null,
        ];

        if ($questionType === Question::TYPE_MCQ) {
            $answerAttributes['selected_option_id'] = $payload['selected_option_id'] ?? null;
            $answerAttributes['answer_text'] = null;
            $answerAttributes['answer_json'] = null;
        } elseif ($questionType === Question::TYPE_STRUCTURED_RESPONSE) {
            $answerAttributes['selected_option_id'] = null;
            $answerAttributes['answer_text'] = null;
            $answerAttributes['answer_json'] = $this->normalizeStructuredAnswers($payload['structured_answers'] ?? []);
        } else {
            $answerAttributes['answer_text'] = isset($payload['answer_text'])
                ? trim((string) $payload['answer_text'])
                : null;
            $answerAttributes['selected_option_id'] = null;
            $answerAttributes['answer_json'] = null;
        }

        $answerAttributes['grading_status'] = StudentAnswer::STATUS_PENDING;

        $answer = $quiz->quizQuestions()
            ->whereKey($quizQuestion->id)
            ->firstOrFail()
            ->studentAnswer()
            ->updateOrCreate([], $answerAttributes);

        $quiz->markInteracted();

        return $answer;
    }

    private function normalizeStructuredAnswers(array $input): array
    {
        return collect($input)
            ->mapWithKeys(fn ($value, $key) => [(string) $key => trim((string) $value)])
            ->all();
    }
}
