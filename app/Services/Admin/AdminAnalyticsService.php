<?php

namespace App\Services\Admin;

use App\Models\ImportRow;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\StudentAnswer;
use App\Models\User;
use Carbon\CarbonInterface;

class AdminAnalyticsService
{
    public function summarize(int $activeWindowDays = 14): array
    {
        $quizStatuses = Quiz::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as started_count', [Quiz::STATUS_IN_PROGRESS])
            ->selectRaw('SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END) as submitted_count', [Quiz::STATUS_SUBMITTED, Quiz::STATUS_GRADING, Quiz::STATUS_GRADED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as graded_count', [Quiz::STATUS_GRADED])
            ->first();

        $pendingGradingCount = StudentAnswer::query()
            ->whereIn('grading_status', [StudentAnswer::STATUS_PENDING, StudentAnswer::STATUS_PROCESSING])
            ->count();

        $manualReviewQuery = StudentAnswer::query()
            ->where('grading_status', StudentAnswer::STATUS_MANUAL_REVIEW);

        $pendingManualReviewCount = (clone $manualReviewQuery)->count();
        $oldestManualReview = (clone $manualReviewQuery)->oldest('created_at')->first(['created_at']);

        $activeWindowStart = now('UTC')->subDays($activeWindowDays);

        $activeStudentsCount = User::query()
            ->where('role', User::ROLE_STUDENT)
            ->whereHas('quizzes', function ($query) use ($activeWindowStart): void {
                $query->where(function ($windowQuery) use ($activeWindowStart): void {
                    $windowQuery
                        ->where('created_at', '>=', $activeWindowStart)
                        ->orWhere('submitted_at', '>=', $activeWindowStart)
                        ->orWhere('last_interacted_at', '>=', $activeWindowStart);
                });
            })
            ->count();

        $gradedStatuses = [StudentAnswer::STATUS_GRADED, StudentAnswer::STATUS_OVERRIDDEN];

        $subjectPerformance = StudentAnswer::query()
            ->join('quiz_questions', 'quiz_questions.id', '=', 'student_answers.quiz_question_id')
            ->join('questions', 'questions.id', '=', 'student_answers.question_id')
            ->join('subjects', 'subjects.id', '=', 'questions.subject_id')
            ->whereIn('student_answers.grading_status', $gradedStatuses)
            ->where('quiz_questions.max_score', '>', 0)
            ->select('subjects.id as subject_id', 'subjects.name as subject_name')
            ->selectRaw('COUNT(student_answers.id) as attempts')
            ->selectRaw('AVG((student_answers.score / quiz_questions.max_score) * 100) as average_score')
            ->groupBy('subjects.id', 'subjects.name')
            ->orderBy('average_score')
            ->orderByDesc('attempts')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'subject_id' => (int) $row->subject_id,
                'subject_name' => $row->subject_name,
                'attempts' => (int) $row->attempts,
                'average_score' => round((float) $row->average_score, 1),
            ]);

        $topicPerformance = StudentAnswer::query()
            ->join('quiz_questions', 'quiz_questions.id', '=', 'student_answers.quiz_question_id')
            ->join('questions', 'questions.id', '=', 'student_answers.question_id')
            ->leftJoin('topics', 'topics.id', '=', 'questions.topic_id')
            ->join('subjects', 'subjects.id', '=', 'questions.subject_id')
            ->whereIn('student_answers.grading_status', $gradedStatuses)
            ->where('quiz_questions.max_score', '>', 0)
            ->whereNotNull('questions.topic_id')
            ->select('topics.id as topic_id', 'topics.name as topic_name', 'subjects.name as subject_name')
            ->selectRaw('COUNT(student_answers.id) as attempts')
            ->selectRaw('AVG((student_answers.score / quiz_questions.max_score) * 100) as average_score')
            ->groupBy('topics.id', 'topics.name', 'subjects.name')
            ->orderBy('average_score')
            ->orderByDesc('attempts')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'topic_id' => (int) $row->topic_id,
                'topic_name' => $row->topic_name,
                'subject_name' => $row->subject_name,
                'attempts' => (int) $row->attempts,
                'average_score' => round((float) $row->average_score, 1),
            ]);

        $weakAreas = collect($topicPerformance)
            ->filter(fn (array $topic) => $topic['attempts'] >= 3)
            ->take(5)
            ->values();

        $publishedQuestions = Question::query()->where('is_published', true)->count();
        $unpublishedQuestions = Question::query()->where('is_published', false)->count();
        $flaggedQuestions = Question::query()
            ->whereNotNull('moderation_flags')
            ->where('moderation_flags', '!=', '[]')
            ->count();

        $recentlyImportedQuestions = ImportRow::query()
            ->where('status', ImportRow::STATUS_IMPORTED)
            ->whereNotNull('related_question_id')
            ->where('created_at', '>=', now('UTC')->subDays(7))
            ->distinct('related_question_id')
            ->count('related_question_id');

        $duplicateSuspectedQuestions = Question::query()
            ->withModerationFlag(Question::FLAG_DUPLICATE_SUSPECTED)
            ->count();

        return [
            'operational' => [
                'total_quizzes_started' => (int) ($quizStatuses?->started_count ?? 0),
                'total_quizzes_submitted' => (int) ($quizStatuses?->submitted_count ?? 0),
                'total_quizzes_graded' => (int) ($quizStatuses?->graded_count ?? 0),
                'pending_grading_count' => $pendingGradingCount,
                'pending_manual_review_count' => $pendingManualReviewCount,
                'oldest_manual_review_created_at' => $oldestManualReview?->created_at,
                'active_students_recent_count' => $activeStudentsCount,
                'active_students_window_days' => $activeWindowDays,
                'subject_performance' => $subjectPerformance,
                'topic_performance' => $topicPerformance,
                'weak_areas' => $weakAreas,
            ],
            'content_health' => [
                'published_questions' => $publishedQuestions,
                'unpublished_questions' => $unpublishedQuestions,
                'flagged_questions' => $flaggedQuestions,
                'recently_imported_questions' => $recentlyImportedQuestions,
                'duplicate_suspected_questions' => $duplicateSuspectedQuestions,
            ],
        ];
    }

    public function oldestManualReviewAge(?CarbonInterface $createdAt): ?string
    {
        if (! $createdAt) {
            return null;
        }

        return $createdAt->diffForHumans(now('UTC'), [
            'parts' => 2,
            'short' => true,
            'syntax' => CarbonInterface::DIFF_ABSOLUTE,
        ]);
    }
}
