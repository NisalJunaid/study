<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word());

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'color' => fake()->hexColor(),
            'icon' => null,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }
}
