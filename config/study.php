<?php

return [
    'queues' => [
        'imports' => env('IMPORT_PROCESSING_QUEUE', 'default'),
        'notifications' => env('NOTIFICATIONS_QUEUE', 'default'),
    ],

    'notifications' => [
        'enabled' => env('STUDY_NOTIFICATIONS_ENABLED', true),
        'student' => [
            'enabled' => env('STUDY_STUDENT_REMINDERS_ENABLED', true),
            'inactivity' => [
                'enabled' => env('STUDY_REMINDER_INACTIVITY_ENABLED', true),
                'days' => (int) env('STUDY_REMINDER_INACTIVITY_DAYS', 7),
                'cooldown_hours' => (int) env('STUDY_REMINDER_INACTIVITY_COOLDOWN_HOURS', 24),
            ],
            'pending_verification' => [
                'enabled' => env('STUDY_REMINDER_PENDING_VERIFICATION_ENABLED', true),
                'minimum_pending_hours' => (int) env('STUDY_REMINDER_PENDING_MIN_HOURS', 12),
                'cooldown_hours' => (int) env('STUDY_REMINDER_PENDING_COOLDOWN_HOURS', 24),
            ],
            'draft_quiz' => [
                'enabled' => env('STUDY_REMINDER_DRAFT_QUIZ_ENABLED', true),
                'inactive_minutes' => (int) env('STUDY_REMINDER_DRAFT_INACTIVE_MINUTES', 120),
                'cooldown_hours' => (int) env('STUDY_REMINDER_DRAFT_COOLDOWN_HOURS', 24),
            ],
        ],
        'admin' => [
            'enabled' => env('STUDY_ADMIN_ALERTS_ENABLED', true),
            'cooldown_hours' => (int) env('STUDY_ADMIN_ALERTS_COOLDOWN_HOURS', 6),
            'manual_review_backlog_threshold' => (int) env('STUDY_ADMIN_MANUAL_REVIEW_THRESHOLD', 20),
            'grading_failure_window_hours' => (int) env('STUDY_ADMIN_GRADING_FAILURE_WINDOW_HOURS', 24),
            'import_failure_window_hours' => (int) env('STUDY_ADMIN_IMPORT_FAILURE_WINDOW_HOURS', 24),
        ],
    ],
];
