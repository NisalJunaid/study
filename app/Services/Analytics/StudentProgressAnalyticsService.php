<?php

namespace App\Services\Analytics;

use App\Models\Quiz;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection;

class StudentProgressAnalyticsService
{
    public function summarize(User $student): array
    {
        $studentId = (int) $student->id;

        $baseQuizQuery = Quiz::query()->forUser($studentId);

        $completedStatuses = [Quiz::STATUS_SUBMITTED, Quiz::STATUS_GRADING, Quiz::STATUS_GRADED];
        $gradedAnswerStatuses = [StudentAnswer::STATUS_GRADED, StudentAnswer::STATUS_OVERRIDDEN];

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
            ->get()
            ->map(function ($subject) {
                $subject->average_score = $subject->average_score !== null ? round((float) $subject->average_score, 1) : null;
                $subject->color = Subject::normalizeColor($subject->color);

                return $subject;
            })
            ->values();

        $topicPerformance = StudentAnswer::query()
            ->join('quiz_questions', 'quiz_questions.id', '=', 'student_answers.quiz_question_id')
            ->join('questions', 'questions.id', '=', 'student_answers.question_id')
            ->leftJoin('topics', 'topics.id', '=', 'questions.topic_id')
            ->join('subjects', 'subjects.id', '=', 'questions.subject_id')
            ->where('student_answers.user_id', $studentId)
            ->whereIn('student_answers.grading_status', $gradedAnswerStatuses)
            ->select(
                'subjects.id as subject_id',
                'subjects.name as subject_name',
                'subjects.color as subject_color',
                'topics.id as topic_id',
                'topics.name as topic_name'
            )
            ->selectRaw('COUNT(student_answers.id) as attempts')
            ->selectRaw('AVG(CASE WHEN quiz_questions.max_score > 0 AND student_answers.score IS NOT NULL THEN (student_answers.score / quiz_questions.max_score) * 100 END) as average_score')
            ->groupBy('subjects.id', 'subjects.name', 'subjects.color', 'topics.id', 'topics.name')
            ->orderBy('average_score')
            ->get()
            ->filter(fn ($row) => ! is_null($row->topic_id))
            ->map(function ($row) {
                $row->average_score = $row->average_score !== null ? round((float) $row->average_score, 1) : null;
                $row->subject_color = Subject::normalizeColor($row->subject_color);

                return $row;
            })
            ->values();

        $weakTopics = $topicPerformance
            ->filter(fn ($topic) => (int) $topic->attempts >= 2)
            ->sortBy('average_score')
            ->take(6)
            ->values();

        $recentActivityAll = (clone $baseQuizQuery)
            ->with('subject:id,name,color')
            ->whereIn('status', $completedStatuses)
            ->latest('submitted_at')
            ->latest('id')
            ->limit(40)
            ->get(['id', 'subject_id', 'mode', 'status', 'submitted_at', 'total_possible_score', 'total_awarded_score']);

        $recentActivityPreview = $recentActivityAll->take(5)->values();

        $timingSummary = StudentAnswer::query()
            ->where('user_id', $studentId)
            ->whereNotNull('answered_on_time')
            ->selectRaw('SUM(CASE WHEN answered_on_time = 1 THEN 1 ELSE 0 END) as on_time_answers')
            ->selectRaw('SUM(CASE WHEN answered_on_time = 0 THEN 1 ELSE 0 END) as late_answers')
            ->selectRaw('COUNT(*) as measured_answers')
            ->first();

        $onTimeAnswers = (int) ($timingSummary?->on_time_answers ?? 0);
        $lateAnswers = (int) ($timingSummary?->late_answers ?? 0);
        $measuredAnswers = (int) ($timingSummary?->measured_answers ?? 0);
        $onTimeRate = $measuredAnswers > 0
            ? round(($onTimeAnswers / $measuredAnswers) * 100, 1)
            : null;

        $strongestSubject = $subjectPerformance
            ->filter(fn ($subject) => (int) $subject->attempts >= 2 && $subject->average_score !== null)
            ->sortByDesc('average_score')
            ->first();

        $weakestSubject = $subjectPerformance
            ->filter(fn ($subject) => (int) $subject->attempts >= 2 && $subject->average_score !== null)
            ->sortBy('average_score')
            ->first();

        $quizTrend = (clone $baseQuizQuery)
            ->whereIn('status', $completedStatuses)
            ->whereNotNull('submitted_at')
            ->whereNotNull('total_awarded_score')
            ->where('total_possible_score', '>', 0)
            ->orderByDesc('submitted_at')
            ->limit(12)
            ->get(['id', 'submitted_at', 'total_awarded_score', 'total_possible_score'])
            ->sortBy('submitted_at')
            ->values();

        $scoreTrend = [
            'labels' => $quizTrend->map(fn (Quiz $quiz) => optional($quiz->submitted_at)->format('M d'))->all(),
            'values' => $quizTrend->map(fn (Quiz $quiz) => round((((float) $quiz->total_awarded_score) / max((float) $quiz->total_possible_score, 0.01)) * 100, 1))->all(),
        ];

        $accuracyByQuiz = StudentAnswer::query()
            ->join('quiz_questions', 'quiz_questions.id', '=', 'student_answers.quiz_question_id')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_questions.quiz_id')
            ->where('student_answers.user_id', $studentId)
            ->whereIn('student_answers.grading_status', $gradedAnswerStatuses)
            ->whereIn('quizzes.status', $completedStatuses)
            ->whereNotNull('quizzes.submitted_at')
            ->where('quiz_questions.max_score', '>', 0)
            ->select('quizzes.id as quiz_id', 'quizzes.submitted_at')
            ->selectRaw('AVG((student_answers.score / quiz_questions.max_score) * 100) as accuracy')
            ->groupBy('quizzes.id', 'quizzes.submitted_at')
            ->orderByDesc('quizzes.submitted_at')
            ->limit(12)
            ->get()
            ->sortBy('submitted_at')
            ->values();

        $accuracyTrend = [
            'labels' => $accuracyByQuiz->map(fn ($item) => optional($item->submitted_at)->format('M d'))->all(),
            'values' => $accuracyByQuiz->map(fn ($item) => round((float) $item->accuracy, 1))->all(),
        ];

        $weekBuckets = collect(range(7, 0))->mapWithKeys(function ($weeksAgo): array {
            $start = now()->startOfWeek()->subWeeks($weeksAgo - 1);
            return [$start->format('Y-m-d') => ['label' => $start->format('M d'), 'count' => 0]];
        });

        $weeklyQuizzes = (clone $baseQuizQuery)
            ->whereIn('status', $completedStatuses)
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', now()->startOfWeek()->subWeeks(7))
            ->get(['submitted_at']);

        $quizCountsByWeek = $weeklyQuizzes
            ->groupBy(fn (Quiz $quiz) => optional($quiz->submitted_at)->copy()->startOfWeek()->format('Y-m-d'))
            ->map(fn (Collection $group) => $group->count());

        foreach ($quizCountsByWeek as $weekStart => $count) {
            if ($weekBuckets->has($weekStart)) {
                $weekBuckets[$weekStart]['count'] = (int) $count;
            }
        }

        $quizzesByPeriod = [
            'labels' => $weekBuckets->pluck('label')->values()->all(),
            'values' => $weekBuckets->pluck('count')->values()->all(),
        ];

        $subjectComparison = [
            'labels' => $subjectPerformance->pluck('name')->all(),
            'values' => $subjectPerformance->map(fn ($subject) => (float) ($subject->average_score ?? 0))->all(),
            'colors' => $subjectPerformance->map(fn ($subject) => $subject->color)->all(),
        ];

        $timingRatio = [
            'labels' => ['On time', 'Late'],
            'values' => [$onTimeAnswers, $lateAnswers],
        ];

        $topicDistribution = [
            'labels' => $topicPerformance
                ->sortByDesc('attempts')
                ->take(8)
                ->map(fn ($topic) => $topic->topic_name)
                ->all(),
            'values' => $topicPerformance
                ->sortByDesc('attempts')
                ->take(8)
                ->map(fn ($topic) => (float) ($topic->average_score ?? 0))
                ->all(),
        ];

        $weakSubjects = $this->buildWeakSubjects($subjectPerformance, $topicPerformance);

        return [
            'summary' => [
                'total_quizzes' => (int) ($summary?->total_quizzes ?? 0),
                'completed_quizzes' => (int) ($summary?->completed_quizzes ?? 0),
                'in_progress_quizzes' => (int) ($summary?->in_progress_quizzes ?? 0),
                'average_score_percentage' => $summary?->average_score_percentage !== null
                    ? round((float) $summary->average_score_percentage, 1)
                    : null,
                'average_accuracy_percentage' => $accuracyByQuiz->isNotEmpty()
                    ? round((float) $accuracyByQuiz->avg('accuracy'), 1)
                    : null,
                'on_time_answer_rate' => $onTimeRate,
                'strongest_subject' => $strongestSubject,
                'weakest_subject' => $weakestSubject,
            ],
            'subject_performance' => $subjectPerformance,
            'weak_topics' => $weakTopics,
            'weak_subjects' => $weakSubjects,
            'topic_performance' => $topicPerformance,
            'recent_activity' => $recentActivityPreview,
            'recent_activity_all' => $recentActivityAll,
            'charts' => [
                'score_trend' => $scoreTrend,
                'accuracy_trend' => $accuracyTrend,
                'quizzes_by_period' => $quizzesByPeriod,
                'subject_comparison' => $subjectComparison,
                'timing_ratio' => $timingRatio,
                'topic_distribution' => $topicDistribution,
            ],
            'insights' => [
                'is_low_data' => (int) ($summary?->completed_quizzes ?? 0) < 2,
                'measured_answers' => $measuredAnswers,
            ],
        ];
    }

    private function buildWeakSubjects(Collection $subjectPerformance, Collection $topicPerformance): Collection
    {
        return $subjectPerformance
            ->filter(fn ($subject) => (int) $subject->attempts >= 2 && $subject->average_score !== null)
            ->sortBy('average_score')
            ->take(3)
            ->map(function ($subject) use ($topicPerformance) {
                $weakTopics = $topicPerformance
                    ->where('subject_id', $subject->id)
                    ->filter(fn ($topic) => (int) $topic->attempts >= 2)
                    ->sortBy('average_score')
                    ->take(3)
                    ->values()
                    ->map(fn ($topic) => [
                        'id' => (int) $topic->topic_id,
                        'name' => $topic->topic_name,
                        'attempts' => (int) $topic->attempts,
                        'average_score' => (float) ($topic->average_score ?? 0),
                    ]);

                return [
                    'id' => (int) $subject->id,
                    'name' => $subject->name,
                    'color' => $subject->color,
                    'attempts' => (int) $subject->attempts,
                    'average_score' => (float) ($subject->average_score ?? 0),
                    'weak_topics' => $weakTopics,
                ];
            })
            ->values();
    }
}
