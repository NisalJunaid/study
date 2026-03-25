<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalOverlayMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_shared_layout_renders_global_overlay_payload_from_session(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->withSession([
                'overlay' => [
                    'title' => 'Trial complete',
                    'message' => 'Choose a plan to continue.',
                    'variant' => 'warning',
                    'primary_label' => 'Choose a Plan',
                ],
            ])
            ->get(route('student.billing.subscription'))
            ->assertOk()
            ->assertSee('data-global-overlay', false)
            ->assertSee('Trial complete')
            ->assertSee('Choose a plan to continue.');
    }

    public function test_invalid_overlay_payload_does_not_render_initial_overlay_data_attribute(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->withSession([
                'overlay' => [
                    'primary_label' => 'Continue',
                    'blocking' => true,
                ],
            ])
            ->get(route('student.billing.subscription'))
            ->assertOk()
            ->assertSee('data-global-overlay', false)
            ->assertDontSee('data-initial-overlay', false);
    }

    public function test_redirect_overlay_payload_includes_redirect_target_in_shell_markup(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->withSession([
                'overlay' => [
                    'title' => 'Payment required',
                    'message' => 'Continue to billing to regain access.',
                    'variant' => 'warning',
                    'primary_label' => 'Go to Payment',
                    'redirect_url' => route('student.billing.payment'),
                    'primary_url' => route('student.billing.payment'),
                    'blocking' => true,
                    'dismissible' => false,
                ],
            ])
            ->get(route('student.billing.subscription'))
            ->assertOk()
            ->assertSee('data-initial-overlay', false)
            ->assertSee(route('student.billing.payment'));
    }


    public function test_legacy_alert_flash_bars_are_not_rendered_in_layout(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->withSession([
                'error' => 'Your free trial has ended.',
            ])
            ->get(route('student.billing.subscription'))
            ->assertOk()
            ->assertDontSee('alert alert-error', false)
            ->assertDontSee('alert alert-success', false)
            ->assertSee('data-global-overlay', false);
    }

    public function test_success_flash_message_does_not_auto_promote_to_global_overlay(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->withSession([
                'success' => 'Quiz created successfully.',
            ])
            ->get(route('student.billing.subscription'))
            ->assertOk()
            ->assertDontSee('data-initial-overlay', false);
    }

    public function test_quiz_take_script_does_not_use_native_browser_dialogs(): void
    {
        $script = file_get_contents(resource_path('js/pages/quiz-take.js'));

        $this->assertIsString($script);
        $this->assertStringNotContainsString('window.alert(', $script);
        $this->assertStringNotContainsString('window.confirm(', $script);
        $this->assertStringNotContainsString('window.prompt(', $script);
    }
}
