<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Analytics\StudentProgressAnalyticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function __invoke(Request $request, StudentProgressAnalyticsService $analyticsService): View
    {
        $analytics = $analyticsService->summarize($request->user());

        return view('pages.student.progress.index', [
            'summary' => $analytics['summary'],
            'streak' => $analytics['streak'],
            'dailyGoal' => $analytics['daily_goal'],
            'subjectPerformance' => $analytics['subject_performance'],
            'weakTopics' => $analytics['weak_topics'],
            'weakSubjects' => $analytics['weak_subjects'],
            'recommendations' => $analytics['recommendations'],
            'topicPerformance' => $analytics['topic_performance'],
            'recentActivity' => $analytics['recent_activity'],
            'recentActivityAll' => $analytics['recent_activity_all'],
            'charts' => $analytics['charts'],
            'insights' => $analytics['insights'],
        ]);
    }
}
