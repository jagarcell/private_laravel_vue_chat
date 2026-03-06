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
     * Logic:
     * 1) Capture sender/recipient identity for targeted delivery.
     * 2) Store type discriminator used by frontend branching logic.
     * 3) Attach optional room/invite metadata when relevant.
     *
     * @param  int  $to_user_id  Target user ID that receives this event.
     * @param  int  $from_user_id  Source user ID that triggered this event.
     * @param  string  $from_user_name  Source user display name.
     * @param  string  $type  Message type (`requested`, `accepted`, `declined`, `closed`, `chat_room_invited`, `chat_room_invite_accepted`, `chat_room_closed`, `chat_room_user_left`).
     * @param  int|null  $invite_id  Related room invite ID for room invite events.
     * @param  int|null  $room_id  Related chat room ID for room invite events.
     * @param  string|null  $room_name  Related chat room name for room invite events.
     * @return void
     */
    public function __construct(
        public int $to_user_id,
        public int $from_user_id,
        public string $from_user_name,
        public string $type,
        public ?int $invite_id = null,
        public ?int $room_id = null,
        public ?string $room_name = null,
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
     * Logic:
     * 1) Return a stable event alias consumed by Echo listeners.
     * 2) Keep one event name for all chat-request lifecycle payloads.
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
     * 3) Include optional room/invite fields for specific flows.
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
            'invite_id' => $this->invite_id,
            'room_id' => $this->room_id,
            'room_name' => $this->room_name,
        ];
    }
}
