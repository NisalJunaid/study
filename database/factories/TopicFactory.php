<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Topic>
 */
class TopicFactory extends Factory
{
    protected $model = Topic::class;

    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'subject_id' => Subject::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }
}
