<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts chat-request lifecycle messages to a specific recipient user.
 */
class ChatRequestMessage implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new chat request message event.
     *
     * @param  int  $to_user_id  Target user ID that receives this event.
     * @param  int  $from_user_id  Source user ID that triggered this event.
     * @param  string  $from_user_name  Source user display name.
     * @param  string  $type  Message type (`requested`, `accepted`, `declined`, `closed`).
     * @return void
     */
    public function __construct(
        public int $to_user_id,
        public int $from_user_id,
        public string $from_user_name,
        public string $type,
    ) {}

    /**
     * Resolve the private broadcast channel for the recipient user.
     *
     * Logic:
     * 1) Build a user-scoped private channel name.
     * 2) Return the channel so only the target user can receive the event.
     *
     * @return PrivateChannel
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("users.chat-request.{$this->to_user_id}");
    }

    /**
     * Define the frontend event name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'chat.request.message';
    }

    /**
     * Provide the event payload delivered to frontend listeners.
     *
     * Logic:
     * 1) Include target/source user identity fields.
     * 2) Include message type so clients can branch UI behavior.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'to_user_id' => $this->to_user_id,
            'from_user_id' => $this->from_user_id,
            'from_user_name' => $this->from_user_name,
            'type' => $this->type,
        ];
    }
}
