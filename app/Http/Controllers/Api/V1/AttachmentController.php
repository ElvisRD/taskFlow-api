<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attachment\StoreAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * The disk where the attachments are stored.
     */
    private const DISK = 'local';

    /**
     * List the attachments of the task.
     */
    public function index(Task $task): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Attachment::class, $task]);

        return AttachmentResource::collection(
            $task->attachments()->with('uploader')->get(),
        );
    }

    /**
     * Upload an attachment to the task.
     */
    public function store(StoreAttachmentRequest $request, Task $task): JsonResponse
    {
        $file = $request->file('file');

        $attachment = $task->attachments()->create([
            'user_id' => $request->user()->getKey(),
            'name' => basename($file->getClientOriginalName()),
            'path' => $file->store('attachments', self::DISK),
            'mime_type' => $file->getMimeType() ?? $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return (new AttachmentResource($attachment->load('uploader')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Download the stored attachment.
     */
    public function download(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        abort_unless(Storage::disk(self::DISK)->exists($attachment->path), 404);

        return Storage::disk(self::DISK)->download($attachment->path, $attachment->name);
    }

    /**
     * Delete the attachment and its stored file.
     */
    public function destroy(Attachment $attachment): Response
    {
        Gate::authorize('delete', $attachment);

        Storage::disk(self::DISK)->delete($attachment->path);

        $attachment->delete();

        return response()->noContent();
    }
}
