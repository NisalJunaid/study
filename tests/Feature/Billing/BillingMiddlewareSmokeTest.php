<?php

namespace Tests\Feature\Billing;

use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingMiddlewareSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_quiz_access_middleware_redirects_blocked_student_to_billing(): void
    {
        $this->withoutVite();

        $student = User::factory()->student()->create([
            'onboarding_intent' => User::ONBOARDING_SUBSCRIBE,
        ]);

        $subject = Subject::factory()->create([
            'level' => Subject::LEVEL_O,
            'is_active' => true,
        ]);

        Question::factory()->mcq()->for($subject)->create([
            'topic_id' => null,
            'is_published' => true,
            'marks' => 1,
        ]);

        $response = $this->actingAs($student)->post(route('student.quiz.store'), [
            'levels' => [Subject::LEVEL_O],
            'multi_subject_mode' => false,
            'subject_id' => $subject->id,
            'mode' => 'mcq',
            'question_count' => 1,
        ]);

        $response->assertRedirect(route('student.billing.subscription'));
    }
}
