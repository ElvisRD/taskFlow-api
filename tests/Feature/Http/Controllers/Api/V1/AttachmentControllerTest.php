<?php

namespace Tests\Feature\Http\Controllers\Api\V1;

use App\Models\Attachment;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\WorkspaceRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    /**
     * @return array{0: Workspace, 1: Task}
     */
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
