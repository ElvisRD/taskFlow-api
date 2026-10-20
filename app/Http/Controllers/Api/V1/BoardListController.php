<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BoardList\StoreBoardListRequest;
use App\Http\Requests\BoardList\UpdateBoardListRequest;
use App\Http\Resources\BoardListResource;
use App\Models\Board;
use App\Models\BoardList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BoardListController extends Controller
{
    /**
     * Create a list inside the board.
     */
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

    /**
     * Update the list.
     */
    public function update(UpdateBoardListRequest $request, Board $board, BoardList $list): BoardListResource
    {
        $list->update($request->safe()->only(['name', 'position']));

        return new BoardListResource($list->loadCount('tasks'));
    }

    /**
     * Delete the list along with its tasks.
     */
    public function destroy(Board $board, BoardList $list): Response
    {
        Gate::authorize('delete', $list);

        $list->delete();

        return response()->noContent();
    }

    /**
     * Get the position a new list should take inside the board.
     */
    private function nextPosition(Board $board): int
    {
        return (int) $board->lists()->reorder()->max('position') + 1;
    }
}
