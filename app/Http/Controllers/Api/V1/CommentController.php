<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    /**
     * List the comments of the task.
     */
    public function index(Request $request, Task $task): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Comment::class, $task]);

        $perPage = max(1, min($request->integer('per_page', 30), 100));

        return CommentResource::collection(
            $task->comments()->with('author')->paginate($perPage),
        );
    }

    /**
     * Add a comment to the task.
     */
    public function store(StoreCommentRequest $request, Task $task): JsonResponse
    {
        $comment = $task->comments()->create([
            'user_id' => $request->user()->getKey(),
            'body' => $request->string('body')->trim()->toString(),
        ]);

        return (new CommentResource($comment->load('author')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Delete the comment.
     */
    public function destroy(Comment $comment): Response
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return response()->noContent();
    }
}
