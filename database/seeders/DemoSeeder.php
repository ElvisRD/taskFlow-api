<?php

namespace Database\Seeders;

use App\Models\Attachment;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\TaskPriority;
use App\WorkspaceRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::updateOrCreate(
            ['email' => 'ana@taskflow.test'],
            [
                'name' => 'Ana Propietaria',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        $member = User::updateOrCreate(
            ['email' => 'beto@taskflow.test'],
            [
                'name' => 'Beto Miembro',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        $workspace = Workspace::factory()->create([
            'owner_id' => $owner->getKey(),
            'name' => 'TaskFlow Demo',
            'slug' => 'taskflow-demo',
            'description' => 'Workspace de demostración para la API de TaskFlow.',
        ]);

        $members = User::query()
            ->whereIn('email', [
                'beto@taskflow.test',
                'carla@taskflow.test',
                'diego@taskflow.test',
                'elena@taskflow.test',
                'fabio@taskflow.test',
            ])
            ->get();

        $workspace->members()->syncWithoutDetaching(
            $members->mapWithKeys(fn (User $workspaceMember): array => [
                $workspaceMember->getKey() => ['role' => WorkspaceRole::Member->value],
            ])->all(),
        );

        $board = Board::factory()->for($workspace)->create([
            'name' => 'Sprint 1',
            'description' => 'Tablero principal del primer sprint.',
            'color' => '#6366f1',
            'position' => 1,
        ]);

        $lists = collect(['Por hacer', 'En progreso', 'Hecho'])
            ->map(fn (string $name, int $index): BoardList => BoardList::factory()->for($board)->create([
                'name' => $name,
                'position' => $index + 1,
            ]));

        $tasks = [
            ['title' => 'Diseñar el modelo de datos', 'priority' => TaskPriority::High, 'list' => 0, 'assignee' => $owner, 'due' => now()->addWeek()],
            ['title' => 'Construir endpoints de autenticación', 'priority' => TaskPriority::Urgent, 'list' => 1, 'assignee' => $member, 'due' => now()->addDays(3)],
            ['title' => 'Maquetar el tablero Kanban en React', 'priority' => TaskPriority::Medium, 'list' => 1, 'assignee' => $member, 'due' => null],
            ['title' => 'Configurar el CI con las pruebas', 'priority' => TaskPriority::Low, 'list' => 0, 'assignee' => null, 'due' => null],
            ['title' => 'Preparar el README del proyecto', 'priority' => TaskPriority::Low, 'list' => 2, 'assignee' => $owner, 'due' => null, 'completed' => true],
        ];

        foreach ($tasks as $index => $data) {
            $task = Task::factory()->for($lists[$data['list']], 'list')->create([
                'created_by' => $owner->getKey(),
                'assigned_to' => $data['assignee']?->getKey(),
                'title' => $data['title'],
                'priority' => $data['priority'],
                'due_date' => $data['due'],
                'completed_at' => ($data['completed'] ?? false) ? now() : null,
                'position' => $index + 1,
            ]);

            Comment::factory()->for($task)->create([
                'user_id' => $member->getKey(),
                'body' => 'Reviso esta tarea hoy mismo.',
            ]);
        }

        $firstTask = Task::query()->orderBy('id')->first();

        Storage::disk('local')->put('attachments/demo.txt', 'Adjunto de ejemplo de TaskFlow.');

        Attachment::factory()->for($firstTask)->create([
            'user_id' => $owner->getKey(),
            'name' => 'demo.txt',
            'path' => 'attachments/demo.txt',
            'mime_type' => 'text/plain',
            'size' => 34,
        ]);
    }
}
