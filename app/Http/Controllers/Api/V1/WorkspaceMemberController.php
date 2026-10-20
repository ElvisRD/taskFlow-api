<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreMemberRequest;
use App\Http\Requests\Workspace\UpdateMemberRequest;
use App\Http\Resources\WorkspaceMemberResource;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class WorkspaceMemberController extends Controller
{
    /**
     * List the members of the workspace.
     */
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

    /**
     * Add an existing user to the workspace.
     */
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

    /**
     * Update the role of a workspace member.
     */
    public function update(UpdateMemberRequest $request, Workspace $workspace, User $user): WorkspaceMemberResource
    {
        abort_unless($this->member($workspace, $user) !== null, 404);

        $workspace->members()->updateExistingPivot($user->getKey(), [
            'role' => $request->string('role')->toString(),
        ]);

        return new WorkspaceMemberResource($this->member($workspace, $user));
    }

    /**
     * Remove a member from the workspace.
     */
    public function destroy(Workspace $workspace, User $user): Response
    {
        Gate::authorize('manageMembers', $workspace);

        abort_if($workspace->isOwnedBy($user), 403, 'No puedes eliminar al propietario del workspace.');
        abort_unless($this->member($workspace, $user) !== null, 404);

        $workspace->members()->detach($user->getKey());

        return response()->noContent();
    }

    /**
     * Resolve the membership of the user inside the workspace.
     */
    private function member(Workspace $workspace, User $user): ?User
    {
        return $workspace->members()->whereKey($user->getKey())->first();
    }
}
