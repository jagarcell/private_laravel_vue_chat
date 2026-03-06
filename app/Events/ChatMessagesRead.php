<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts read receipts for direct chat messages to the original sender.
 */
class ChatMessagesRead implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new read-receipt event instance.
     *
     * Logic:
     * 1) Capture which sender should be notified.
     * 2) Capture which recipient read the messages.
     * 3) Include message IDs and read timestamp for client-side updates.
     *
     * @param  int  $notify_user_id
     * @param  int  $reader_user_id
     * @param  array<int, int>  $message_ids
     * @param  string  $read_at
     * @return void
     */
    public function __construct(
        public int $notify_user_id,
        public int $reader_user_id,
        public array $message_ids,
        public string $read_at,
    ) {}

    /**
     * Resolve the private channel for the sender being notified.
     *
     * Logic:
     * 1) Scope channel to one user.
     * 2) Return the user-specific chat-message private channel.
     *
     * @return PrivateChannel
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("users.chat-message.{$this->notify_user_id}");
    }

    /**
     * Provide the frontend event name consumed by Echo listeners.
     *
     * Logic:
     * 1) Return a stable alias for read-receipt subscriptions.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'chat.messages.read';
    }

    /**
     * Build the read-receipt payload delivered to the sender client.
     *
     * Logic:
     * 1) Include reader identity.
     * 2) Include message IDs that became read.
     * 3) Include the read timestamp to set receipt state in UI.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'reader_user_id' => $this->reader_user_id,
            'message_ids' => $this->message_ids,
            'read_at' => $this->read_at,
        ];
    }
}
