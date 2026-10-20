<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Board\ReorderBoardRequest;
use App\Http\Requests\Board\StoreBoardRequest;
use App\Http\Requests\Board\UpdateBoardRequest;
use App\Http\Resources\BoardResource;
use App\Models\Board;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BoardController extends Controller
{
    public function reorder(ReorderBoardRequest $request, Workspace $workspace): AnonymousResourceCollection
    {
        $ids = array_map('intval', $request->input('boards', []));

        DB::transaction(function () use ($workspace, $ids): void {
            foreach ($ids as $index => $id) {
                $workspace->boards()->whereKey($id)->update(['position' => $index + 1]);
            }
        });

        return BoardResource::collection(
            $workspace->boards()->withCount('lists')->orderBy('position')->get(),
        );
    }

    public function index(Request $request, Workspace $workspace): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Board::class, $workspace]);

        $perPage = max(1, min($request->integer('per_page', 15), 100));
        $search = $request->string('search')->trim()->toString();

        $boards = $workspace->boards()
            ->withCount('lists')
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('position')
            ->orderBy('name')
            ->paginate($perPage);

        return BoardResource::collection($boards);
    }

    public function store(StoreBoardRequest $request, Workspace $workspace): JsonResponse
    {
        $board = $workspace->boards()->create([
            'name' => $request->string('name')->trim()->toString(),
            'description' => $request->input('description'),
            'color' => $request->input('color'),
            'position' => $request->has('position')
                ? $request->integer('position')
                : $this->nextPosition($workspace),
        ]);

        return (new BoardResource($board->loadCount('lists')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Workspace $workspace, Board $board): BoardResource
    {
        Gate::authorize('view', $board);

        $board->load([
            'lists' => fn ($query) => $query->withCount('tasks'),
            'lists.tasks' => fn ($query) => $query
                ->with(['creator', 'assignee'])
                ->withCount(['comments', 'attachments']),
        ])->loadCount('lists');

        return new BoardResource($board);
    }

    public function update(UpdateBoardRequest $request, Workspace $workspace, Board $board): BoardResource
    {
        $board->update($request->safe()->only(['name', 'description', 'color', 'position']));

        return new BoardResource($board->loadCount('lists'));
    }

    public function destroy(Workspace $workspace, Board $board): Response
    {
        Gate::authorize('delete', $board);

        $board->delete();

        return response()->noContent();
    }

    private function nextPosition(Workspace $workspace): int
    {
        return (int) $workspace->boards()->max('position') + 1;
    }
}
