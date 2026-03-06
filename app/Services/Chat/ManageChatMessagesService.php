<?php

namespace App\Services\Chat;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Encapsulates direct chat message persistence and retrieval rules.
 */
class ManageChatMessagesService
{
    /**
     * Persist and broadcast a direct chat message.
     *
     * Logic:
     * 1) Reject self-targeted messages.
     * 2) Ensure the recipient is online.
     * 3) Persist the message row as unread for recipient.
     * 4) Broadcast realtime event to recipient channel.
     *
     * @param  User  $fromUser
     * @param  int  $toUserId
     * @param  string  $message
     * @return ChatMessage
     */
    public function send(User $fromUser, int $toUserId, string $message): ChatMessage
    {
        $this->ensureDifferentUsers(
            fromUserId: (int) $fromUser->id,
            toUserId: $toUserId,
            errorMessage: 'You cannot send a message to yourself.',
        );

        if (! $this->isUserOnline($toUserId)) {
            throw new InvalidArgumentException('You can only send messages to online users.');
        }

        $chatMessage = ChatMessage::query()->create([
            'from_user_id' => (int) $fromUser->id,
            'to_user_id' => $toUserId,
            'message' => $message,
            'read_at' => null,
        ]);

        $chatMessage->loadMissing(['fromUser:id,name']);

        event(new ChatMessageSent(
            chat_message_id: (int) $chatMessage->id,
            to_user_id: $toUserId,
            from_user_id: (int) $fromUser->id,
            from_user_name: (string) $fromUser->name,
            message: $message,
            created_at: (string) $chatMessage->created_at,
        ));

        return $chatMessage;
    }

    /**
     * Fetch a conversation between the authenticated user and another user.
     *
     * Logic:
     * 1) Select messages where participants match either direction.
     * 2) Order by creation timestamp ascending.
     * 3) Eager-load sender relation used by API resource.
     *
     * @param  User  $authenticatedUser
     * @param  User  $otherUser
     * @param  int  $limit
     * @return Collection<int, ChatMessage>
     */
    public function conversation(User $authenticatedUser, User $otherUser, int $limit = 200): Collection
    {
        return ChatMessage::query()
            ->with(['fromUser:id,name'])
            ->where(function ($query) use ($authenticatedUser, $otherUser): void {
                $query
                    ->where('from_user_id', $authenticatedUser->id)
                    ->where('to_user_id', $otherUser->id);
            })
            ->orWhere(function ($query) use ($authenticatedUser, $otherUser): void {
                $query
                    ->where('from_user_id', $otherUser->id)
                    ->where('to_user_id', $authenticatedUser->id);
            })
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Return unread incoming message counters grouped by sender.
     *
     * Logic:
     * 1) Select unread rows targeted to authenticated user.
     * 2) Group by sender user ID.
     * 3) Return sender/count pairs for API projection.
     *
     * @param  User  $authenticatedUser
     * @return array<int, array<string, int>>
     */
    public function unreadCounts(User $authenticatedUser): array
    {
        return ChatMessage::query()
            ->selectRaw('from_user_id, COUNT(*) as unread_count')
            ->where('to_user_id', $authenticatedUser->id)
            ->whereNull('read_at')
            ->groupBy('from_user_id')
            ->orderBy('from_user_id')
            ->get()
            ->map(function (ChatMessage $chatMessage): array {
                return [
                    'user_id' => (int) $chatMessage->from_user_id,
                    'unread_count' => (int) $chatMessage->unread_count,
                ];
            })
            ->all();
    }

    /**
     * Mark unread incoming messages from one user as read.
     *
     * Logic:
     * 1) Scope to unread messages from other user to authenticated user.
     * 2) Update `read_at` with current timestamp.
     * 3) Return number of updated rows.
     *
     * @param  User  $authenticatedUser
     * @param  User  $otherUser
     * @return int
     */
    public function markConversationAsRead(User $authenticatedUser, User $otherUser): int
    {
        return ChatMessage::query()
            ->where('from_user_id', $otherUser->id)
            ->where('to_user_id', $authenticatedUser->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);
    }

    /**
     * Validate that source and destination users are different.
     *
        * Logic:
        * 1) Compare sender and recipient IDs.
        * 2) Throw a domain validation exception when IDs match.
        *
     * @param  int  $fromUserId
     * @param  int  $toUserId
     * @param  string  $errorMessage
     * @return void
     */
    private function ensureDifferentUsers(int $fromUserId, int $toUserId, string $errorMessage): void
    {
        if ($fromUserId === $toUserId) {
            throw new InvalidArgumentException($errorMessage);
        }
    }

    /**
     * Determine whether a target user is currently online based on active sessions.
     *
        * Logic:
        * 1) Require database session driver and sessions table availability.
        * 2) Compute minimum active timestamp from session lifetime config.
        * 3) Check whether target user has any active session row.
        *
     * @param  int  $userId
     * @return bool
     */
    private function isUserOnline(int $userId): bool
    {
        if (config('session.driver') !== 'database' || ! Schema::hasTable('sessions')) {
            return false;
        }

        $minimumLastActivity = now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp();

        return DB::table('sessions')
            ->where('user_id', $userId)
            ->where('last_activity', '>=', $minimumLastActivity)
            ->exists();
    }
}
