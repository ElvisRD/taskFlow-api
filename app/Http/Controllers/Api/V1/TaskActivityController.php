<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskActivityResource;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskActivityController extends Controller
{
    public function index(Request $request, Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        $perPage = max(1, min($request->integer('per_page', 30), 100));

        return TaskActivityResource::collection(
            $task->activities()->with('user')->paginate($perPage),
        );
    }
}
