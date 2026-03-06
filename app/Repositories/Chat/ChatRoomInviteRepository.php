<?php

namespace App\Repositories\Chat;

use App\Models\ChatRoom;
use App\Models\ChatRoomInvite;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Handles persistence and query operations for chat-room invitations.
 */
class ChatRoomInviteRepository
{
    /**
     * Create pending invites for selected users in one room.
     *
     * Logic:
     * 1) Iterate selected invitee user IDs.
     * 2) Insert one pending invite row per invitee.
     * 3) Return all created invite models as a collection.
     *
     * @param  ChatRoom  $chatRoom
     * @param  User  $fromUser
     * @param  array<int, int>  $toUserIds
     * @return Collection<int, ChatRoomInvite>
     */
    public function createPending(ChatRoom $chatRoom, User $fromUser, array $toUserIds): Collection
    {
        $invites = collect($toUserIds)->map(function (int $toUserId) use ($chatRoom, $fromUser): ChatRoomInvite {
            return ChatRoomInvite::query()->create([
                'chat_room_id' => (int) $chatRoom->id,
                'from_user_id' => (int) $fromUser->id,
                'to_user_id' => $toUserId,
                'status' => 'pending',
                'responded_at' => null,
            ]);
        });

        return new Collection($invites->all());
    }

    /**
     * Return pending invites for one user.
     *
     * Logic:
     * 1) Scope invites to authenticated user as recipient.
     * 2) Filter to pending status only.
     * 3) Eager-load room/requester metadata used by API resources.
     *
     * @param  User  $authenticatedUser
     * @return Collection<int, ChatRoomInvite>
     */
    public function pendingForUser(User $authenticatedUser): Collection
    {
        return ChatRoomInvite::query()
            ->with(['chatRoom:id,name', 'fromUser:id,name'])
            ->where('to_user_id', $authenticatedUser->id)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Find one invite scoped to pending state and invitee ownership.
     *
     * Logic:
     * 1) Scope lookup by invite ID, invitee, and pending status.
     * 2) Eager-load related room and user metadata.
     * 3) Throw a domain exception when no valid pending invite is found.
     *
     * @param  int  $inviteId
     * @param  User  $authenticatedUser
     * @return ChatRoomInvite
     */
    public function findPendingForInvitee(int $inviteId, User $authenticatedUser): ChatRoomInvite
    {
        $invite = ChatRoomInvite::query()
            ->with(['chatRoom:id,name', 'fromUser:id,name', 'toUser:id,name'])
            ->where('id', $inviteId)
            ->where('to_user_id', $authenticatedUser->id)
            ->where('status', 'pending')
            ->first();

        if (is_null($invite)) {
            throw new InvalidArgumentException('Invite not found or already handled.');
        }

        return $invite;
    }

    /**
     * Update invite status to accepted/declined.
     *
     * Logic:
     * 1) Persist new status value on invite row.
     * 2) Set `responded_at` timestamp.
     * 3) Refresh and return updated model state.
     *
     * @param  ChatRoomInvite  $invite
     * @param  string  $status
     * @return ChatRoomInvite
     */
    public function markStatus(ChatRoomInvite $invite, string $status): ChatRoomInvite
    {
        $invite->forceFill([
            'status' => $status,
            'responded_at' => now(),
        ])->save();

        return $invite->refresh();
    }
}
