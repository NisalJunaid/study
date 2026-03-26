<?php

namespace Tests\Unit;

use App\Models\Question;
use App\Services\AI\ModelRoutingService;
use Tests\TestCase;

class ModelRoutingServiceTest extends TestCase
{
    public function test_mcq_is_marked_as_local_only_without_ai(): void
    {
        $route = app(ModelRoutingService::class)->resolve([
            'question_type' => Question::TYPE_MCQ,
            'student_answer' => 'A',
        ]);

        $this->assertFalse($route['use_ai']);
        $this->assertSame('local_only', $route['tier']);
    }

    public function test_complex_theory_routes_to_high_accuracy_model(): void
    {
        config()->set('openai.models.low_cost', 'gpt-low');
        config()->set('openai.models.high_accuracy', 'gpt-high');

        $route = app(ModelRoutingService::class)->resolve([
            'question_type' => Question::TYPE_THEORY,
            'question' => 'Explain in detail.',
            'student_answer' => str_repeat('long answer ', 60),
            'sample_answer' => str_repeat('expected detail ', 40),
            'strict_semantic' => true,
        ]);

        $this->assertTrue($route['use_ai']);
        $this->assertSame('high_accuracy', $route['tier']);
        $this->assertSame('gpt-high', $route['model']);
    }
}
