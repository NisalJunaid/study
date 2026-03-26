<?php

namespace Tests\Feature;

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
}
