<?php

namespace Tests\Unit;

use App\Http\Resources\NotificationResource;
use App\Notifications\TaskAssigned;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NotificationResourceTest extends TestCase
{
    public function test_it_exposes_the_payload_and_unread_state(): void
    {
        $notification = $this->notification(readAt: null);

        $array = (new NotificationResource($notification))->toArray(Request::create('/'));

        $this->assertSame('notification-1', $array['id']);
        $this->assertSame('TaskAssigned', $array['type']);
        $this->assertSame(['type' => 'task_assigned', 'task_id' => 10], $array['payload']);
        $this->assertNull($array['read_at']);
        $this->assertFalse($array['is_read']);
        $this->assertEquals(Carbon::parse('2026-10-20 10:00:00'), $array['created_at']);
    }

    public function test_it_exposes_the_read_state(): void
    {
        $notification = $this->notification(readAt: Carbon::parse('2026-10-20 11:00:00'));

        $array = (new NotificationResource($notification))->toArray(Request::create('/'));

        $this->assertNotNull($array['read_at']);
        $this->assertTrue($array['is_read']);
    }

    private function notification(?Carbon $readAt): DatabaseNotification
    {
        $notification = new DatabaseNotification([
            'id' => 'notification-1',
            'type' => TaskAssigned::class,
            'data' => ['type' => 'task_assigned', 'task_id' => 10],
            'read_at' => $readAt,
        ]);
        $notification->created_at = Carbon::parse('2026-10-20 10:00:00');

        return $notification;
    }
}
