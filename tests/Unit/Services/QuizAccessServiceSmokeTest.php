<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\Billing\QuizAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizAccessServiceSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_returns_expected_shape_for_student(): void
    {
        $student = User::factory()->student()->create();

        $result = app(QuizAccessService::class)->evaluate($student, 1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('access_type', $result);
        $this->assertArrayHasKey('message', $result);
    }
}
