<?php

namespace Tests\Feature;

use App\Models\PaymentSetting;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppShellNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_student_sidebar_only_contains_study_navigation_links(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student)->get(route('student.quiz.setup'));
        $response->assertOk();

        preg_match('/<nav class="nav-list" data-student-nav>(.*?)<\/nav>/s', $response->getContent(), $matches);
        $this->assertNotEmpty($matches, 'Student navigation block should be rendered.');

        $studentNav = $matches[1];

        $this->assertStringContainsString('Build Quiz', $studentNav);
        $this->assertStringContainsString('History', $studentNav);
        $this->assertStringContainsString('Progress', $studentNav);
        $this->assertStringContainsString('Results', $studentNav);

        $this->assertStringNotContainsString('Billing', $studentNav);
        $this->assertStringNotContainsString('Profile', $studentNav);
        $this->assertStringNotContainsString('Settings', $studentNav);
        $this->assertStringNotContainsString('Logout', $studentNav);
    }

    public function test_student_user_dropdown_contains_account_actions_including_billing(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('student.quiz.setup'))
            ->assertOk()
            ->assertSee(route('student.billing.subscription'), false)
            ->assertSee('Profile')
            ->assertSee('Settings')
            ->assertSee('Sign out');
    }

    public function test_admin_user_dropdown_points_billing_to_admin_payments(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.billing.payments.index'), false)
            ->assertSee('Profile')
            ->assertSee('Settings')
            ->assertSee('Sign out');
    }

    public function test_student_header_displays_ai_credits_as_available_over_total(): void
    {
        $student = User::factory()->student()->create();
        $subject = Subject::factory()->create(['is_active' => true]);
        $question = Question::factory()->theory()->create([
            'subject_id' => $subject->id,
            'is_published' => true,
            'topic_id' => null,
        ]);

        PaymentSetting::current()->update(['daily_ai_credits' => 10]);

        $quiz = Quiz::query()->create([
            'user_id' => $student->id,
            'subject_id' => $subject->id,
            'mode' => Quiz::MODE_THEORY,
            'status' => Quiz::STATUS_SUBMITTED,
            'total_questions' => 1,
            'total_possible_score' => 1,
            'started_at' => now(),
            'submitted_at' => now(),
        ]);

        $quiz->quizQuestions()->create([
            'question_id' => $question->id,
            'order_no' => 1,
            'question_snapshot' => ['type' => Question::TYPE_THEORY, 'question_text' => 'Q'],
            'max_score' => 1,
        ]);

        $this->actingAs($student)
            ->get(route('student.quiz.setup'))
            ->assertOk()
            ->assertSee('9 / 10');
    }
}
