<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts a direct chat message to a specific recipient user channel.
 */
class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new chat message event.
     *
     * Logic:
     * 1) Capture sender identity and recipient target.
     * 2) Keep message payload for realtime delivery.
     *
     * @param  int  $to_user_id
     * @param  int  $from_user_id
     * @param  string  $from_user_name
     * @param  string  $message
     * @return void
     */
    public function __construct(
        public int $to_user_id,
        public int $from_user_id,
        public string $from_user_name,
        public string $message,
    ) {}

    /**
     * Resolve the private broadcast channel for the target user.
     *
     * Logic:
     * 1) Build a user-scoped private channel name.
     * 2) Return that channel for authorization-guarded delivery.
     *
     * @return PrivateChannel
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("users.chat-message.{$this->to_user_id}");
    }

    /**
     * Define the frontend event alias.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }

    /**
     * Build the broadcast payload delivered to frontend listeners.
     *
     * Logic:
     * 1) Include sender identity metadata.
     * 2) Include raw message text.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'to_user_id' => $this->to_user_id,
            'from_user_id' => $this->from_user_id,
            'from_user_name' => $this->from_user_name,
            'message' => $this->message,
        ];
    }
}
