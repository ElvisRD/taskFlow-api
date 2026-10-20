<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $perPage = max(1, min($request->integer('per_page', 20), 100));

        return NotificationResource::collection(
            $user->notifications()->latest()->paginate($perPage),
        )->additional([
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, DatabaseNotification $notification): NotificationResource
    {
        abort_unless($this->owns($request, $notification), 404);

        $notification->markAsRead();

        return new NotificationResource($notification);
    }

    public function readAll(Request $request): Response
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->noContent();
    }

    private function owns(Request $request, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_type === $request->user()::class
            && (int) $notification->notifiable_id === (int) $request->user()->getKey();
    }
}
