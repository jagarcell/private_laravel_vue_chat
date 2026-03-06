<?php

namespace App\Services\Chat;

use App\Events\ChatRequestMessage;
use App\Models\ChatRoom;
use App\Models\ChatRoomInvite;
use App\Models\User;
use App\Repositories\Chat\ChatRoomInviteRepository;
use App\Repositories\Chat\ChatRoomRepository;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Encapsulates chat-room persistence and retrieval rules.
 */
class ManageChatRoomsService
{
    /**
     * Create a new service instance.
     *
     * Logic:
     * 1) Inject chat-room repository for persistence/query operations.
     * 2) Inject invite repository for invite lifecycle handling.
     * 3) Keep orchestration and broadcast logic in service layer.
     *
     * @param  ChatRoomRepository  $chatRoomRepository
     * @param  ChatRoomInviteRepository  $chatRoomInviteRepository
     * @return void
     */
    public function __construct(
        private readonly ChatRoomRepository $chatRoomRepository,
        private readonly ChatRoomInviteRepository $chatRoomInviteRepository,
    ) {}

    /**
     * Return chat rooms visible to one user.
     *
     * Logic:
     * 1) Scope room retrieval to memberships for the authenticated user.
     * 2) Delegate query execution to repository layer.
     *
     * @param  User  $authenticatedUser
     * @return Collection<int, ChatRoom>
     */
    public function listForUser(User $authenticatedUser): Collection
    {
        return $this->chatRoomRepository->listForUser($authenticatedUser);
    }

    /**
     * Create a chat room with selected participants and the creator.
     *
     * Logic:
     * 1) Normalize participant IDs to unique positive integers.
     * 2) Create room with creator as initial participant.
     * 3) Create pending invites for selected users.
     * 4) Broadcast invite events to each invitee.
     *
     * @param  User  $authenticatedUser
     * @param  string  $name
     * @param  array<int, int|string>  $participantUserIds
     * @return ChatRoom
     */
    public function create(User $authenticatedUser, string $name, array $participantUserIds): ChatRoom
    {
        $normalizedUserIds = collect($participantUserIds)
            ->map(static fn ($userId): int => (int) $userId)
            ->filter(static fn (int $userId): bool => $userId > 0)
            ->reject(fn (int $userId): bool => $userId === (int) $authenticatedUser->id)
            ->unique()
            ->values()
            ->all();

        $chatRoom = $this->chatRoomRepository->create(
            creator: $authenticatedUser,
            name: trim($name),
            participantUserIds: [(int) $authenticatedUser->id],
        );

        $invites = $this->chatRoomInviteRepository->createPending($chatRoom, $authenticatedUser, $normalizedUserIds);

        foreach ($invites as $invite) {
            event(new ChatRequestMessage(
                to_user_id: (int) $invite->to_user_id,
                from_user_id: (int) $authenticatedUser->id,
                from_user_name: (string) $authenticatedUser->name,
                type: 'chat_room_invited',
                invite_id: (int) $invite->id,
                room_id: (int) $chatRoom->id,
                room_name: (string) $chatRoom->name,
            ));
        }

        return $chatRoom;
    }

    /**
     * Return pending invites for the authenticated user.
     *
     * Logic:
     * 1) Scope invite retrieval to invites addressed to authenticated user.
     * 2) Delegate pending-invite query execution to repository layer.
     *
     * @param  User  $authenticatedUser
     * @return Collection<int, ChatRoomInvite>
     */
    public function pendingInvitesForUser(User $authenticatedUser): Collection
    {
        return $this->chatRoomInviteRepository->pendingForUser($authenticatedUser);
    }

    /**
     * Respond to one pending chat-room invite.
     *
     * Logic:
     * 1) Resolve invite and enforce recipient ownership/pending state.
     * 2) Mark invite as accepted/declined.
     * 3) Attach user to room when accepted.
     *
     * @param  User  $authenticatedUser
     * @param  int  $inviteId
     * @param  string  $action
     * @return array{invite:ChatRoomInvite,chat_room:ChatRoom|null}
     */
    public function respondToInvite(User $authenticatedUser, int $inviteId, string $action): array
    {
        $invite = $this->chatRoomInviteRepository->findPendingForInvitee($inviteId, $authenticatedUser);
        $status = $action === 'accept' ? 'accepted' : 'declined';

        if (! in_array($status, ['accepted', 'declined'], true)) {
            throw new InvalidArgumentException('Invalid invite response action.');
        }

        $updatedInvite = $this->chatRoomInviteRepository->markStatus($invite, $status);
        $chatRoom = null;

        if ($status === 'accepted') {
            $chatRoom = $this->chatRoomRepository->addParticipant($invite->chatRoom, (int) $authenticatedUser->id);

            event(new ChatRequestMessage(
                to_user_id: (int) $invite->from_user_id,
                from_user_id: (int) $authenticatedUser->id,
                from_user_name: (string) $authenticatedUser->name,
                type: 'chat_room_invite_accepted',
                invite_id: (int) $updatedInvite->id,
                room_id: (int) $invite->chat_room_id,
                room_name: (string) ($invite->chatRoom?->name ?? 'Chat Room'),
            ));
        }

        return [
            'invite' => $updatedInvite,
            'chat_room' => $chatRoom,
        ];
    }

    /**
     * Close a chat room as its creator and notify remaining participants.
     *
     * Logic:
     * 1) Resolve room scoped to authenticated participant membership.
     * 2) Ensure only room creator can close the room.
     * 3) Broadcast close notification to all other participants.
     * 4) Delete room and all dependent data via cascade rules.
     *
     * @param  User  $authenticatedUser
     * @param  int  $chatRoomId
     * @return void
     */
    public function closeRoom(User $authenticatedUser, int $chatRoomId): void
    {
        $chatRoom = $this->chatRoomRepository->findForParticipant($chatRoomId, $authenticatedUser);

        if ((int) $chatRoom->created_by_user_id !== (int) $authenticatedUser->id) {
            throw new InvalidArgumentException('Only the room creator can close this chat room.');
        }

        $participantIds = $chatRoom->users
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(fn (int $userId): bool => $userId !== (int) $authenticatedUser->id)
            ->values()
            ->all();

        foreach ($participantIds as $participantId) {
            event(new ChatRequestMessage(
                to_user_id: $participantId,
                from_user_id: (int) $authenticatedUser->id,
                from_user_name: (string) $authenticatedUser->name,
                type: 'chat_room_closed',
                room_id: (int) $chatRoom->id,
                room_name: (string) $chatRoom->name,
            ));
        }

        $this->chatRoomRepository->delete($chatRoom);
    }

    /**
     * Leave a chat room as invited participant and notify remaining users.
     *
     * Logic:
     * 1) Resolve room scoped to authenticated participant membership.
     * 2) Prevent creator from leaving (creator can close room instead).
     * 3) Remove authenticated user from room membership.
     * 4) Broadcast leave notification to remaining participants.
     *
     * @param  User  $authenticatedUser
     * @param  int  $chatRoomId
     * @return void
     */
    public function leaveRoom(User $authenticatedUser, int $chatRoomId): void
    {
        $chatRoom = $this->chatRoomRepository->findForParticipant($chatRoomId, $authenticatedUser);
        $this->leaveRoomAndNotify($authenticatedUser, $chatRoom);
    }

    /**
     * Leave one room and broadcast leave events to remaining participants.
     *
     * @param  User  $authenticatedUser
     * @param  ChatRoom  $chatRoom
     * @return void
     */
    private function leaveRoomAndNotify(User $authenticatedUser, ChatRoom $chatRoom): void
    {
        if ((int) $chatRoom->created_by_user_id === (int) $authenticatedUser->id) {
            throw new InvalidArgumentException('Room creator cannot leave the room. Close it instead.');
        }

        $remainingParticipantIds = $chatRoom->users
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(fn (int $userId): bool => $userId !== (int) $authenticatedUser->id)
            ->values()
            ->all();

        $this->chatRoomRepository->removeParticipant($chatRoom, (int) $authenticatedUser->id);

        foreach ($remainingParticipantIds as $participantId) {
            event(new ChatRequestMessage(
                to_user_id: $participantId,
                from_user_id: (int) $authenticatedUser->id,
                from_user_name: (string) $authenticatedUser->name,
                type: 'chat_room_user_left',
                room_id: (int) $chatRoom->id,
                room_name: (string) $chatRoom->name,
            ));
        }
    }
}
