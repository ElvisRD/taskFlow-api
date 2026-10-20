<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Http\Resources\WorkspaceResource;
use App\Models\User;
use App\Models\Workspace;
use App\WorkspaceRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class WorkspaceController extends Controller
{
    /**
     * List the workspaces the authenticated user belongs to.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', 15), 100));
        $search = $request->string('search')->trim()->toString();

        $workspaces = $request->user()->workspaces()
            ->with('owner')
            ->withCount(['members', 'boards'])
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->paginate($perPage);

        return WorkspaceResource::collection($workspaces);
    }

    /**
     * Create a new workspace and add the creator as its owner.
     */
    public function store(StoreWorkspaceRequest $request): JsonResponse
    {
        $user = $request->user();

        $workspace = DB::transaction(function () use ($request, $user): Workspace {
            $workspace = Workspace::create([
                'owner_id' => $user->getKey(),
                'name' => $request->string('name')->trim()->toString(),
                'slug' => $this->uniqueSlug($request->string('name')->toString()),
                'description' => $request->input('description'),
            ]);

            $workspace->members()->attach($user->getKey(), [
                'role' => WorkspaceRole::Admin->value,
            ]);

            return $workspace;
        });

        return $this->resource($user, $workspace)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a single workspace.
     */
    public function show(Request $request, Workspace $workspace): WorkspaceResource
    {
        Gate::authorize('view', $workspace);

        return $this->resource($request->user(), $workspace);
    }

    /**
     * Update the workspace.
     */
    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): WorkspaceResource
    {
        $workspace->update($request->safe()->only(['name', 'description']));

        return $this->resource($request->user(), $workspace);
    }

    /**
     * Delete the workspace.
     */
    public function destroy(Workspace $workspace): Response
    {
        Gate::authorize('delete', $workspace);

        $workspace->delete();

        return response()->noContent();
    }

    /**
     * Build the workspace resource with its owner, counters and the role of the current user.
     */
    private function resource(User $user, Workspace $workspace): WorkspaceResource
    {
        $workspace = $user->workspaces()
            ->with('owner')
            ->withCount(['members', 'boards'])
            ->whereKey($workspace->getKey())
            ->firstOrFail();

        return new WorkspaceResource($workspace);
    }

    /**
     * Build a unique slug for the given workspace name.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $suffix = 2;

        while (Workspace::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
