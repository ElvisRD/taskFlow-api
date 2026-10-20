<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Notifications\CommentReply;
use App\Notifications\TaskCommented;
use App\TaskActivityType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function index(Request $request, Task $task): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Comment::class, $task]);

        $perPage = max(1, min($request->integer('per_page', 30), 100));

        return CommentResource::collection(
            $task->comments()
                ->whereNull('parent_id')
                ->with([
                    'author',
                    'replies' => fn ($query) => $query->with('author'),
                ])
                ->paginate($perPage),
        );
    }

    public function store(StoreCommentRequest $request, Task $task): JsonResponse
    {
        $comment = $task->comments()->create([
            'parent_id' => $request->input('parent_id'),
            'user_id' => $request->user()->getKey(),
            'body' => $request->string('body')->trim()->toString(),
        ]);

        $task->recordActivity(TaskActivityType::CommentAdded, $request->user(), [
            'comment_id' => $comment->getKey(),
        ]);

        $this->notifyParticipants($task, $comment, $request->user());

        return (new CommentResource($comment->load('author')))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Comment $comment): Response
    {
        Gate::authorize('delete', $comment);

        $comment->delete();

        return response()->noContent();
    }

    private function notifyParticipants(Task $task, Comment $comment, User $actor): void
    {
        $replyRecipient = $comment->parent?->author;

        $recipients = collect([$task->creator, $task->assignee])
            ->filter()
            ->unique(fn (User $user): int => $user->getKey())
            ->reject(fn (User $user): bool => $user->is($actor));

        if ($replyRecipient !== null && ! $replyRecipient->is($actor)) {
            $replyRecipient->notify(new CommentReply($task, $comment, $actor));

            $recipients = $recipients->reject(fn (User $user): bool => $user->is($replyRecipient));
        }

        $recipients->each(fn (User $user) => $user->notify(new TaskCommented($task, $comment, $actor)));
    }
}
