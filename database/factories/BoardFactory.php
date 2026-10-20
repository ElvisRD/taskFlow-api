<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BoardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => Str::title(fake()->unique()->words(2, true)),
            'description' => fake()->sentence(),
            'color' => fake()->hexColor(),
            'position' => 0,
        ];
    }
}
