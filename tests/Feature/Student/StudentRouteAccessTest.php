<?php

namespace Tests\Feature\Student;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentRouteAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_student_quiz_setup(): void
    {
        $this->withoutVite();

        $this->get(route('student.quiz.setup'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_student_can_access_quiz_setup(): void
    {
        $this->withoutVite();

        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('student.quiz.setup'))
            ->assertOk();
    }
}
