<?php

namespace Database\Factories;

use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'subject_id' => Subject::factory(),
            'topic_id' => Topic::factory(),
            'type' => Question::TYPE_MCQ,
            'question_text' => fake()->sentence() . '?',
            'question_image_path' => null,
            'difficulty' => fake()->randomElement(['easy', 'medium', 'hard']),
            'explanation' => fake()->sentence(),
            'marks' => fake()->randomFloat(2, 1, 5),
            'is_published' => true,
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function mcq(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Question::TYPE_MCQ,
        ]);
    }

    public function theory(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Question::TYPE_THEORY,
        ]);
    }

    public function structuredResponse(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Question::TYPE_STRUCTURED_RESPONSE,
        ]);
    }
}
