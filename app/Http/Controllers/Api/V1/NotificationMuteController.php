<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\StoreNotificationMuteRequest;
use App\Http\Resources\NotificationMuteResource;
use App\Models\Board;
use App\Models\NotificationMute;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class NotificationMuteController extends Controller
{
    public function store(StoreNotificationMuteRequest $request): JsonResponse
    {
        $muteable = $this->resolveMuteable(
            $request->string('target_type')->toString(),
            $request->integer('target_id'),
        );

        Gate::authorize('view', $muteable);

        $mute = $request->user()->notificationMutes()->firstOrCreate([
            'muteable_type' => $muteable::class,
            'muteable_id' => $muteable->getKey(),
        ]);

        return (new NotificationMuteResource($mute->load('muteable')))
            ->response()
            ->setStatusCode($mute->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, NotificationMute $mute): Response
    {
        abort_unless(
            (int) $mute->user_id === (int) $request->user()->getKey(),
            404,
        );

        $mute->delete();

        return response()->noContent();
    }

    private function resolveMuteable(string $type, int $id): Workspace|Board
    {
        return match ($type) {
            NotificationMute::WorkspaceTarget => Workspace::findOrFail($id),
            NotificationMute::BoardTarget => Board::findOrFail($id),
        };
    }
}
