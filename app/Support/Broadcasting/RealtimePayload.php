<?php

namespace App\Support\Broadcasting;

use App\Models\Import;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\StudentAnswer;

class RealtimePayload
{
    public static function import(Import $import): array
    {
        return [
            'id' => $import->id,
            'status' => $import->status,
            'total_rows' => (int) $import->total_rows,
            'valid_rows' => (int) $import->valid_rows,
            'imported_rows' => (int) $import->imported_rows,
            'failed_rows' => (int) $import->failed_rows,
            'completed_at' => optional($import->completed_at)->toIso8601String(),
            'updated_at' => optional($import->updated_at)->toIso8601String(),
        ];
    }

    public static function quizProgress(Quiz $quiz): array
    {
        $quiz->loadMissing('quizQuestions.studentAnswer');

        $theoryQuestions = $quiz->quizQuestions
            ->filter(fn (QuizQuestion $quizQuestion) => ($quizQuestion->question_snapshot['type'] ?? null) === 'theory')
            ->values();

        $pendingStatuses = [StudentAnswer::STATUS_PENDING, StudentAnswer::STATUS_PROCESSING];
        $completedTheory = $theoryQuestions->filter(function (QuizQuestion $quizQuestion) use ($pendingStatuses): bool {
            $status = $quizQuestion->studentAnswer?->grading_status;

            return $status !== null && ! in_array($status, $pendingStatuses, true);
        })->count();

        return [
            'id' => $quiz->id,
            'status' => $quiz->status,
            'total_awarded_score' => $quiz->total_awarded_score !== null ? (float) $quiz->total_awarded_score : null,
            'total_possible_score' => (float) $quiz->total_possible_score,
            'graded_at' => optional($quiz->graded_at)->toIso8601String(),
            'submitted_at' => optional($quiz->submitted_at)->toIso8601String(),
            'theory_total' => $theoryQuestions->count(),
            'theory_completed' => $completedTheory,
        ];
    }

    public static function theoryAnswer(StudentAnswer $studentAnswer): array
    {
        $studentAnswer->loadMissing([
            'quizQuestion:id,quiz_id,order_no,max_score',
            'quizQuestion.quiz:id,user_id',
        ]);

        return [
            'id' => $studentAnswer->id,
            'quiz_id' => $studentAnswer->quizQuestion?->quiz_id,
            'quiz_question_id' => $studentAnswer->quiz_question_id,
            'question_order' => $studentAnswer->quizQuestion?->order_no,
            'grading_status' => $studentAnswer->grading_status,
            'is_correct' => $studentAnswer->is_correct,
            'score' => $studentAnswer->score !== null ? (float) $studentAnswer->score : null,
            'max_score' => $studentAnswer->quizQuestion?->max_score !== null ? (float) $studentAnswer->quizQuestion->max_score : null,
            'feedback' => $studentAnswer->feedback,
            'ai_result_json' => $studentAnswer->ai_result_json,
            'graded_at' => optional($studentAnswer->graded_at)->toIso8601String(),
        ];
    }
}
