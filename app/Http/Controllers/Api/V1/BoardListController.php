<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BoardList\ReorderBoardListRequest;
use App\Http\Requests\BoardList\StoreBoardListRequest;
use App\Http\Requests\BoardList\UpdateBoardListRequest;
use App\Http\Resources\BoardListResource;
use App\Models\Board;
use App\Models\BoardList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BoardListController extends Controller
{
    public function reorder(ReorderBoardListRequest $request, Board $board): AnonymousResourceCollection
    {
        $ids = array_map('intval', $request->input('lists', []));

        DB::transaction(function () use ($board, $ids): void {
            foreach ($ids as $index => $id) {
                $board->lists()->whereKey($id)->update(['position' => $index + 1]);
            }
        });

        return BoardListResource::collection(
            $board->lists()->withCount('tasks')->get(),
        );
    }

    public function store(StoreBoardListRequest $request, Board $board): JsonResponse
    {
        $list = $board->lists()->create([
            'name' => $request->string('name')->trim()->toString(),
            'position' => $request->has('position')
                ? $request->integer('position')
                : $this->nextPosition($board),
        ]);

        return (new BoardListResource($list->loadCount('tasks')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateBoardListRequest $request, Board $board, BoardList $list): BoardListResource
    {
        $list->update($request->safe()->only(['name', 'position']));

        return new BoardListResource($list->loadCount('tasks'));
    }

    public function destroy(Board $board, BoardList $list): Response
    {
        Gate::authorize('delete', $list);

        $list->delete();

        return response()->noContent();
    }

    private function nextPosition(Board $board): int
    {
        return (int) $board->lists()->reorder()->max('position') + 1;
    }
}
