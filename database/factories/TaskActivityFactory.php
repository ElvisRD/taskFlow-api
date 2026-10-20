<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use App\TaskActivityType;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'type' => TaskActivityType::Created,
            'data' => null,
        ];
    }
}
