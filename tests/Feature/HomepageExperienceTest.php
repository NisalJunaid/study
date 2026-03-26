<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_homepage_is_the_default_public_landing_page(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Focus Lab')
            ->assertSee('Get Started')
            ->assertSee('Log In');
    }

    public function test_homepage_cta_points_students_to_quiz_builder_when_logged_in(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Build Quiz')
            ->assertDontSee('Get Started');
    }


    public function test_homepage_cta_points_admins_to_admin_dashboard_when_logged_in(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Open Admin Dashboard')
            ->assertDontSee('Get Started');
    }

    public function test_student_dashboard_route_redirects_to_quiz_builder_for_compatibility(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertRedirect(route('student.quiz.setup'));
    }
}
