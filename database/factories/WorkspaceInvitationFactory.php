<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\WorkspaceInvitationStatus;
use App\WorkspaceRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceInvitation>
 */
class WorkspaceInvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'invited_by' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => WorkspaceRole::Member,
            'token' => WorkspaceInvitation::hashToken(WorkspaceInvitation::generateToken()),
            'status' => WorkspaceInvitationStatus::Pending,
            'expires_at' => now()->addWeek(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WorkspaceInvitationStatus::Pending,
            'expires_at' => now()->subDay(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WorkspaceInvitationStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WorkspaceInvitationStatus::Revoked,
            'revoked_at' => now(),
        ]);
    }
}
