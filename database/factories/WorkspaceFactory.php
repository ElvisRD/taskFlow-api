<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use App\WorkspaceRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WorkspaceFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->sentence(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Workspace $workspace): void {
            $workspace->members()->syncWithoutDetaching([
                $workspace->owner_id => ['role' => WorkspaceRole::Admin->value],
            ]);
        });
    }
}
