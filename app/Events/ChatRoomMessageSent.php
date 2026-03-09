<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts a room-scoped chat message to one recipient user channel.
 */
class ChatRoomMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new room message event.
     *
     * Logic:
     * 1) Capture recipient and sender identities.
     * 2) Include room and message payload for realtime room UI updates.
     *
     * @param  int  $to_user_id
     * @param  int  $chat_room_id
     * @param  int  $chat_room_message_id
     * @param  int  $from_user_id
     * @param  string  $from_user_name
     * @param  string  $message
     * @param  string|null  $created_at
     * @return void
     */
    public function __construct(
        public int $to_user_id,
        public int $chat_room_id,
        public int $chat_room_message_id,
        public int $from_user_id,
        public string $from_user_name,
        public string $message,
        public ?string $created_at = null,
    ) {}

    /**
     * Resolve the private broadcast channel for one recipient user.
     *
     * Logic:
     * 1) Scope broadcast delivery to the intended recipient's private channel.
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
     * Logic:
     * 1) Expose a stable event name for Echo listeners in the chat UI.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'chat.room.message.sent';
    }

    /**
     * Build room message broadcast payload.
     *
     * Logic:
     * 1) Send the minimal room message shape required for realtime rendering.
     * 2) Preserve message identity metadata for dedupe and ordering.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->chat_room_message_id,
            'chat_room_id' => $this->chat_room_id,
            'from_user_id' => $this->from_user_id,
            'from_user_name' => $this->from_user_name,
            'message' => $this->message,
            'created_at' => $this->created_at,
        ];
    }
}
