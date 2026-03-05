<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserOnlineStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $user_id,
        public bool $is_online,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('users.status');
    }

    public function broadcastAs(): string
    {
        return 'user.status.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user_id,
            'is_online' => $this->is_online,
        ];
    }
}
