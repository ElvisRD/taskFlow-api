<?php

namespace App\Notifications\Concerns;

trait BroadcastsTaskNotification
{
    /**
     * The notification shape consumed by the web client.
     *
     * @return array<string, mixed>
     */
    abstract protected function notificationPayload(): array;

    /**
     * @return array<string, mixed>
     */
    public function toBroadcast(object $notifiable): array
    {
        return $this->notificationPayload();
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->id,
            'type' => class_basename($this),
            'payload' => $this->notificationPayload(),
            'read_at' => null,
            'is_read' => false,
            'created_at' => now()->toIso8601String(),
        ];
    }
}
