<?php

namespace Database\Factories;

use App\Models\BoardList;
use App\Models\User;
use App\TaskPriority;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'board_list_id' => BoardList::factory(),
            'created_by' => User::factory(),
            'assigned_to' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'position' => 0,
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'due_date' => null,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'completed_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'due_date' => now()->subWeek(),
            'completed_at' => null,
        ]);
    }
}
