<?php

namespace Database\Factories;

use App\Models\Board;
use Illuminate\Database\Eloquent\Factories\Factory;

class BoardListFactory extends Factory
{
    public function definition(): array
    {
        return [
            'board_id' => Board::factory(),
            'name' => fake()->randomElement(['Por hacer', 'En progreso', 'En revisión', 'Hecho']),
            'position' => 0,
        ];
    }
}
