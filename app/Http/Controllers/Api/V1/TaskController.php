<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\MoveTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Task;
use App\TaskPriority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function index(Request $request, Board $board): AnonymousResourceCollection
    {
        Gate::authorize('view', $board);

        $perPage = max(1, min($request->integer('per_page', 15), 100));
        $search = $request->string('search')->trim()->toString();
        $priority = TaskPriority::tryFrom($request->string('priority')->toString());

        $tasks = Task::query()
            ->whereHas('list', fn (Builder $query) => $query->where('board_id', $board->getKey()))
            ->with(['creator', 'assignee'])
            ->withCount(['comments', 'attachments'])
            ->when(
                $request->filled('board_list_id'),
                fn (Builder $query) => $query->where('board_list_id', $request->integer('board_list_id')),
            )
            ->when(
                $request->filled('assigned_to'),
                fn (Builder $query) => $query->where('assigned_to', $request->integer('assigned_to')),
            )
            ->when($priority !== null, fn (Builder $query) => $query->priority($priority))
            ->when($request->has('completed'), fn (Builder $query) => $query->completed($request->boolean('completed')))
            ->when($request->boolean('overdue'), fn (Builder $query) => $query->overdue())
            ->when($search !== '', fn (Builder $query) => $query->search($search))
            ->tap(fn (Builder $query) => $this->applySorting($query, $request))
            ->paginate($perPage);

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request, BoardList $list): JsonResponse
    {
        $task = $list->tasks()->create([
            'created_by' => $request->user()->getKey(),
            'assigned_to' => $request->input('assigned_to'),
            'title' => $request->string('title')->trim()->toString(),
            'description' => $request->input('description'),
            'priority' => $request->string('priority', TaskPriority::Medium->value)->toString(),
            'due_date' => $request->input('due_date'),
            'position' => $request->has('position')
                ? $request->integer('position')
                : $this->nextPosition($list),
        ]);

        return (new TaskResource($this->withDetails($task)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        $task->load(['creator', 'assignee', 'comments.author', 'attachments.uploader'])
            ->loadCount(['comments', 'attachments']);

        return new TaskResource($task);
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $data = $request->safe()->only([
            'title', 'description', 'assigned_to', 'priority', 'due_date', 'position',
        ]);

        DB::transaction(function () use ($request, $task, $data): void {
            if ($request->has('board_list_id')) {
                $data['board_list_id'] = $request->integer('board_list_id');

                if (! $request->has('position')) {
                    $data['position'] = $this->nextPosition(BoardList::findOrFail($data['board_list_id']));
                }
            }

            if ($request->has('completed')) {
                $task->completed_at = $request->boolean('completed')
                    ? ($task->completed_at ?? now())
                    : null;
            }

            $task->fill($data)->save();
        });

        return new TaskResource($this->withDetails($task->refresh()));
    }

    public function destroy(Task $task): Response
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return response()->noContent();
    }

    public function move(MoveTaskRequest $request, Task $task): TaskResource
    {
        $destination = BoardList::findOrFail($request->integer('board_list_id'));

        $task->moveToList($destination, $this->destinationIndex($request, $task, $destination));

        return new TaskResource($this->withDetails($task->refresh()));
    }

    private function destinationIndex(MoveTaskRequest $request, Task $task, BoardList $destination): ?int
    {
        $reference = match (true) {
            $request->filled('before_task_id') => $request->integer('before_task_id'),
            $request->filled('after_task_id') => $request->integer('after_task_id'),
            default => null,
        };

        if ($reference === null) {
            return null;
        }

        $index = Task::query()
            ->where('board_list_id', $destination->getKey())
            ->whereKeyNot($task->getKey())
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->search($reference);

        if ($index === false) {
            return null;
        }

        return $request->filled('after_task_id') ? $index + 1 : $index;
    }

    private function applySorting(Builder $query, Request $request): void
    {
        match ($request->string('sort')->toString()) {
            'due_date' => $query->orderByRaw('due_date is null')->orderBy('due_date'),
            'priority' => $query->orderByPriority($request->string('direction')->toString()),
            'title' => $query->orderBy('title'),
            default => $query->orderBy('board_list_id')->orderBy('position')->orderByDesc('id'),
        };
    }

    private function withDetails(Task $task): Task
    {
        return $task->load(['creator', 'assignee'])->loadCount(['comments', 'attachments']);
    }

    private function nextPosition(BoardList $list): int
    {
        return (int) $list->tasks()->reorder()->max('position') + 1;
    }
}
