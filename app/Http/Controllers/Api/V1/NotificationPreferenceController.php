<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\UpdateNotificationPreferenceRequest;
use App\Http\Resources\NotificationMuteResource;
use App\Http\Resources\NotificationPreferenceResource;
use App\Models\NotificationPreference;
use App\NotificationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $stored = $user->notificationPreferences()
            ->get()
            ->keyBy(fn (NotificationPreference $preference): string => $preference->type->value);

        $preferences = collect(NotificationType::cases())
            ->map(fn (NotificationType $type): NotificationPreference => $stored->get($type->value)
                ?? new NotificationPreference([
                    'type' => $type,
                    'in_app' => true,
                    'email' => true,
                ]));

        return NotificationPreferenceResource::collection($preferences)->additional([
            'mutes' => NotificationMuteResource::collection(
                $user->notificationMutes()->with('muteable')->latest()->orderByDesc('id')->get(),
            ),
        ]);
    }

    public function update(
        UpdateNotificationPreferenceRequest $request,
        NotificationType $type,
    ): JsonResponse {
        $preference = $request->user()->notificationPreferences()->updateOrCreate(
            ['type' => $type->value],
            $request->safe()->only(['in_app', 'email']),
        );

        return (new NotificationPreferenceResource($preference->refresh()))
            ->response()
            ->setStatusCode(200);
    }
}
