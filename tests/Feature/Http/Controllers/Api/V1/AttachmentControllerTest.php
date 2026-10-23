<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Attachment;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\AttachmentAdded;
use App\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttachmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_uploads_an_attachment_to_the_task(): void
    {
        Storage::fake('local');

        [$workspace, $task] = $this->taskInWorkspace();
        $member = $this->addMember($workspace, WorkspaceRole::Member);

        Sanctum::actingAs($member);

        $response = $this->post(
            '/api/v1/tasks/'.$task->id.'/attachments',
            ['file' => UploadedFile::fake()->create('reporte.pdf', 100, 'application/pdf')],
            ['Accept' => 'application/json'],
        );

        $response->assertCreated()
            ->assertJsonPath('data.name', 'reporte.pdf')
            ->assertJsonPath('data.uploader.id', $member->id);

        $attachment = Attachment::firstOrFail();

        $this->assertSame($task->id, $attachment->task_id);
        $this->assertSame($member->id, $attachment->user_id);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_upload_notifies_the_task_assignee(): void
    {
        Storage::fake('local');
        Notification::fake();

        [$workspace, $task] = $this->taskInWorkspace();
        $uploader = $this->addMember($workspace, WorkspaceRole::Member);
        $assignee = $this->addMember($workspace, WorkspaceRole::Member);
        $task->update(['assigned_to' => $assignee->id]);

        Sanctum::actingAs($uploader);

        $this->post(
            '/api/v1/tasks/'.$task->id.'/attachments',
            ['file' => UploadedFile::fake()->create('reporte.pdf', 100, 'application/pdf')],
            ['Accept' => 'application/json'],
        )->assertCreated();

        Notification::assertSentToTimes($assignee, AttachmentAdded::class, 1);
        Notification::assertSentTo(
            $assignee,
            AttachmentAdded::class,
            fn (AttachmentAdded $notification): bool => $notification->task->is($task)
                && $notification->actor->is($uploader)
                && $notification->attachment->name === 'reporte.pdf',
        );
        Notification::assertNotSentTo($uploader, AttachmentAdded::class);
    }

    public function test_upload_does_not_notify_the_assignee_when_they_upload_the_file(): void
    {
        Storage::fake('local');
        Notification::fake();

        [$workspace, $task] = $this->taskInWorkspace();
        $member = $this->addMember($workspace, WorkspaceRole::Member);
        $task->update(['assigned_to' => $member->id]);

        Sanctum::actingAs($member);

        $this->post(
            '/api/v1/tasks/'.$task->id.'/attachments',
            ['file' => UploadedFile::fake()->create('foto.png', 100, 'image/png')],
            ['Accept' => 'application/json'],
        )->assertCreated();

        Notification::assertNothingSent();
    }

    public function test_upload_does_not_notify_when_the_task_has_no_assignee(): void
    {
        Storage::fake('local');
        Notification::fake();

        [$workspace, $task] = $this->taskInWorkspace();

        Sanctum::actingAs($workspace->owner);

        $this->post(
            '/api/v1/tasks/'.$task->id.'/attachments',
            ['file' => UploadedFile::fake()->create('foto.png', 100, 'image/png')],
            ['Accept' => 'application/json'],
        )->assertCreated();

        Notification::assertNothingSent();
    }

    public function test_upload_rejects_a_disallowed_file_type(): void
    {
        Storage::fake('local');

        [$workspace, $task] = $this->taskInWorkspace();

        Sanctum::actingAs($workspace->owner);

        $this->post(
            '/api/v1/tasks/'.$task->id.'/attachments',
            ['file' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload')],
            ['Accept' => 'application/json'],
        )->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_member_downloads_the_attachment(): void
    {
        Storage::fake('local');

        [$workspace, $task] = $this->taskInWorkspace();
        $member = $this->addMember($workspace, WorkspaceRole::Member);
        Storage::disk('local')->put('attachments/reporte.pdf', 'contenido');
        $attachment = Attachment::factory()->for($task)->create([
            'user_id' => $workspace->owner_id,
            'name' => 'reporte.pdf',
            'path' => 'attachments/reporte.pdf',
        ]);

        Sanctum::actingAs($member);

        $this->getJson('/api/v1/attachments/'.$attachment->id.'/download')
            ->assertOk()
            ->assertDownload('reporte.pdf');
    }

    public function test_non_member_cannot_download_the_attachment(): void
    {
        Storage::fake('local');

        [, $task] = $this->taskInWorkspace();
        Storage::disk('local')->put('attachments/reporte.pdf', 'contenido');
        $attachment = Attachment::factory()->for($task)->create([
            'path' => 'attachments/reporte.pdf',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/attachments/'.$attachment->id.'/download')->assertForbidden();
    }

    public function test_uploader_can_delete_the_attachment(): void
    {
        Storage::fake('local');

        [$workspace, $task] = $this->taskInWorkspace();
        $member = $this->addMember($workspace, WorkspaceRole::Member);
        Storage::disk('local')->put('attachments/reporte.pdf', 'contenido');
        $attachment = Attachment::factory()->for($task)->create([
            'user_id' => $member->id,
            'path' => 'attachments/reporte.pdf',
        ]);

        Sanctum::actingAs($member);

        $this->deleteJson('/api/v1/attachments/'.$attachment->id)->assertNoContent();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        Storage::disk('local')->assertMissing('attachments/reporte.pdf');
    }

    public function test_other_member_cannot_delete_the_attachment(): void
    {
        Storage::fake('local');

        [$workspace, $task] = $this->taskInWorkspace();
        $uploader = $this->addMember($workspace, WorkspaceRole::Member);
        $other = $this->addMember($workspace, WorkspaceRole::Member);
        $attachment = Attachment::factory()->for($task)->create(['user_id' => $uploader->id]);

        Sanctum::actingAs($other);

        $this->deleteJson('/api/v1/attachments/'.$attachment->id)->assertForbidden();

        $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
    }

    private function taskInWorkspace(): array
    {
        $workspace = Workspace::factory()->create();
        $board = Board::factory()->for($workspace)->create();
        $list = BoardList::factory()->for($board)->create();
        $task = Task::factory()->for($list, 'list')->create();

        return [$workspace, $task];
    }

    private function addMember(Workspace $workspace, WorkspaceRole $role): User
    {
        $member = User::factory()->create();

        $workspace->members()->attach($member->getKey(), ['role' => $role->value]);

        return $member;
    }
}
