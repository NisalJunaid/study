<?php

namespace App\Services\Analytics;

use App\Models\Quiz;
use App\Models\StudentAnswer;
use App\Models\User;

class StudentProgressAnalyticsService
{
    public function summarize(User $student): array
    {
        $studentId = (int) $student->id;

        $baseQuizQuery = Quiz::query()->forUser($studentId);

        $completedStatuses = [Quiz::STATUS_SUBMITTED, Quiz::STATUS_GRADING, Quiz::STATUS_GRADED];

        $summary = (clone $baseQuizQuery)
            ->selectRaw('COUNT(*) as total_quizzes')
            ->selectRaw('SUM(CASE WHEN status in (?, ?, ?) THEN 1 ELSE 0 END) as completed_quizzes', $completedStatuses)
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress_quizzes', [Quiz::STATUS_IN_PROGRESS])
            ->selectRaw('AVG(CASE WHEN status in (?, ?, ?) AND total_possible_score > 0 AND total_awarded_score IS NOT NULL THEN (total_awarded_score / total_possible_score) * 100 END) as average_score_percentage', $completedStatuses)
            ->first();

        $subjectPerformance = (clone $baseQuizQuery)
            ->join('subjects', 'subjects.id', '=', 'quizzes.subject_id')
            ->select('subjects.id', 'subjects.name', 'subjects.color')
            ->selectRaw('COUNT(quizzes.id) as attempts')
            ->selectRaw('AVG(CASE WHEN quizzes.total_possible_score > 0 AND quizzes.total_awarded_score IS NOT NULL THEN (quizzes.total_awarded_score / quizzes.total_possible_score) * 100 END) as average_score')
            ->whereIn('quizzes.status', $completedStatuses)
            ->groupBy('subjects.id', 'subjects.name', 'subjects.color')
            ->orderByDesc('attempts')
            ->orderByDesc('average_score')
            ->get();

        $weakTopics = StudentAnswer::query()
            ->join('quiz_questions', 'quiz_questions.id', '=', 'student_answers.quiz_question_id')
            ->join('questions', 'questions.id', '=', 'student_answers.question_id')
            ->join('topics', 'topics.id', '=', 'questions.topic_id')
            ->where('student_answers.user_id', $studentId)
            ->whereIn('student_answers.grading_status', [
                StudentAnswer::STATUS_GRADED,
                StudentAnswer::STATUS_OVERRIDDEN,
            ])
            ->select('topics.id', 'topics.name')
            ->selectRaw('COUNT(student_answers.id) as attempts')
            ->selectRaw('AVG(CASE WHEN quiz_questions.max_score > 0 AND student_answers.score IS NOT NULL THEN (student_answers.score / quiz_questions.max_score) * 100 END) as average_score')
            ->groupBy('topics.id', 'topics.name')
            ->havingRaw('COUNT(student_answers.id) >= 2')
            ->orderBy('average_score')
            ->limit(5)
            ->get();

        $recentActivity = (clone $baseQuizQuery)
            ->with('subject:id,name')
            ->whereIn('status', $completedStatuses)
            ->latest('submitted_at')
            ->latest('id')
            ->limit(8)
            ->get(['id', 'subject_id', 'mode', 'status', 'submitted_at', 'total_possible_score', 'total_awarded_score']);

        return [
            'summary' => [
                'total_quizzes' => (int) ($summary?->total_quizzes ?? 0),
                'completed_quizzes' => (int) ($summary?->completed_quizzes ?? 0),
                'in_progress_quizzes' => (int) ($summary?->in_progress_quizzes ?? 0),
                'average_score_percentage' => $summary?->average_score_percentage !== null
                    ? round((float) $summary->average_score_percentage, 1)
                    : null,
            ],
            'subject_performance' => $subjectPerformance,
            'weak_topics' => $weakTopics,
            'recent_activity' => $recentActivity,
        ];
    }
}
