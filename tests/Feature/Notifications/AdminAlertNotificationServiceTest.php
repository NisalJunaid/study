<?php

namespace Tests\Feature\Notifications;

use App\Models\Import;
use App\Models\PaymentSetting;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\StudentAnswer;
use App\Models\User;
use App\Notifications\AdminOperationalAlertNotification;
use App\Services\Notifications\AdminAlertNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminAlertNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_alerts_are_generated_for_operational_anomalies(): void
    {
        Notification::fake();
        Cache::flush();

        config()->set('study.notifications.admin.manual_review_backlog_threshold', 1);

        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $question = Question::factory()->theory()->create();
        $quiz = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $question->subject_id,
            'mode' => Quiz::MODE_THEORY,
            'status' => Quiz::STATUS_GRADING,
            'total_questions' => 1,
            'total_possible_score' => 1,
        ]);
        $quizQuestion = QuizQuestion::query()->create([
            'quiz_id' => $quiz->id,
            'question_id' => $question->id,
            'order_no' => 1,
            'question_snapshot' => ['type' => 'theory', 'question_text' => 'Q'],
            'max_score' => 1,
        ]);

        StudentAnswer::query()->create([
            'quiz_question_id' => $quizQuestion->id,
            'question_id' => $question->id,
            'user_id' => $student->id,
            'answer_text' => 'x',
            'grading_status' => StudentAnswer::STATUS_MANUAL_REVIEW,
            'ai_result_json' => ['manual_review_reason' => 'ai_failed'],
        ]);

        Import::query()->create([
            'uploaded_by' => $admin->id,
            'file_name' => 'import.csv',
            'file_path' => 'imports/import.csv',
            'status' => Import::STATUS_FAILED,
        ]);

        PaymentSetting::query()->delete();

        app(AdminAlertNotificationService::class)->sendAdminAlerts();

        Notification::assertSentTo($admin, AdminOperationalAlertNotification::class);
    }

    public function test_admin_alerts_respect_cooldown_and_avoid_duplicates(): void
    {
        Notification::fake();
        Cache::flush();

        config()->set('study.notifications.admin.manual_review_backlog_threshold', 1);

        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $question = Question::factory()->theory()->create();
        $quiz = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $question->subject_id,
            'mode' => Quiz::MODE_THEORY,
            'status' => Quiz::STATUS_GRADING,
            'total_questions' => 1,
            'total_possible_score' => 1,
        ]);
        $quizQuestion = QuizQuestion::query()->create([
            'quiz_id' => $quiz->id,
            'question_id' => $question->id,
            'order_no' => 1,
            'question_snapshot' => ['type' => 'theory', 'question_text' => 'Q'],
            'max_score' => 1,
        ]);

        StudentAnswer::query()->create([
            'quiz_question_id' => $quizQuestion->id,
            'question_id' => $question->id,
            'user_id' => $student->id,
            'answer_text' => 'x',
            'grading_status' => StudentAnswer::STATUS_MANUAL_REVIEW,
            'ai_result_json' => ['manual_review_reason' => 'ai_failed'],
        ]);

        app(AdminAlertNotificationService::class)->sendAdminAlerts();
        app(AdminAlertNotificationService::class)->sendAdminAlerts();

        Notification::assertSentToTimes($admin, AdminOperationalAlertNotification::class, 1);
    }
}
