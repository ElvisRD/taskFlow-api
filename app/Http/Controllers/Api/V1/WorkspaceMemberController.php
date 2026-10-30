<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\RemoveMemberRequest;
use App\Http\Requests\Workspace\StoreMemberRequest;
use App\Http\Requests\Workspace\UpdateMemberRequest;
use App\Http\Resources\WorkspaceMemberResource;
use App\Models\Board;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\TaskAssigned;
use App\TaskActivityType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WorkspaceMemberController extends Controller
{
    public function index(Request $request, Workspace $workspace): AnonymousResourceCollection
    {
        Gate::authorize('view', $workspace);

        $search = $request->string('search')->trim()->toString();

        $members = $workspace->members()
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->get();

        return WorkspaceMemberResource::collection($members);
    }

    public function store(StoreMemberRequest $request, Workspace $workspace): JsonResponse
    {
        $user = User::whereRaw('LOWER(email) = ?', [
            $request->string('email')->lower()->trim()->toString(),
        ])->firstOrFail();

        $workspace->members()->attach($user->getKey(), [
            'role' => $request->string('role')->toString(),
        ]);

        return (new WorkspaceMemberResource($this->member($workspace, $user)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateMemberRequest $request, Workspace $workspace, User $user): WorkspaceMemberResource
    {
        abort_unless($this->member($workspace, $user) !== null, 404);

        $workspace->members()->updateExistingPivot($user->getKey(), [
            'role' => $request->string('role')->toString(),
        ]);

        return new WorkspaceMemberResource($this->member($workspace, $user));
    }

    public function destroy(RemoveMemberRequest $request, Workspace $workspace, User $user): Response
    {
        abort_if($workspace->isOwnedBy($user), 403, 'No puedes eliminar al propietario del workspace.');
        abort_unless($this->member($workspace, $user) !== null, 404);

        $reassignTo = $request->filled('reassign_to')
            ? User::find($request->integer('reassign_to'))
            : null;

        $tasks = DB::transaction(function () use ($request, $workspace, $user, $reassignTo) {
            $tasks = Task::query()
                ->where('assigned_to', $user->getKey())
                ->whereHas('list.board', fn (Builder $query) => $query->where('workspace_id', $workspace->getKey()))
                ->lockForUpdate()
                ->get();

            foreach ($tasks as $task) {
                $previousAssignee = $task->assigned_to;

                $task->assigned_to = $reassignTo?->getKey();
                $task->save();
                $task->reminders()->delete();

                if ($reassignTo === null) {
                    $task->recordActivity(TaskActivityType::Unassigned, $request->user(), [
                        'assigned_to' => $previousAssignee,
                    ]);
                } else {
                    $task->recordActivity(TaskActivityType::Assigned, $request->user(), [
                        'assigned_to' => $reassignTo->getKey(),
                    ]);
                }
            }

            $workspace->members()->detach($user->getKey());

            $user->notifications()
                ->where('data->workspace_id', $workspace->getKey())
                ->delete();

            $user->notificationMutes()
                ->where(function (Builder $query) use ($workspace): void {
                    $query->where(function (Builder $query) use ($workspace): void {
                        $query->where('muteable_type', Workspace::class)
                            ->where('muteable_id', $workspace->getKey());
                    })->orWhere(function (Builder $query) use ($workspace): void {
                        $query->where('muteable_type', Board::class)
                            ->whereIn('muteable_id', $workspace->boards()->pluck('id'));
                    });
                })
                ->delete();

            return $tasks;
        });

        if ($reassignTo !== null && ! $reassignTo->is($request->user())) {
            $tasks->each(fn (Task $task) => $reassignTo->notify(new TaskAssigned($task, $request->user())));
        }

        return response()->noContent();
    }

    private function member(Workspace $workspace, User $user): ?User
    {
        return $workspace->members()->whereKey($user->getKey())->first();
    }
}
