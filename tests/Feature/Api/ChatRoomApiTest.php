<?php

namespace Tests\Feature\Api;

use App\Models\ChatRoom;
use App\Models\ChatRoomInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verifies chat-room API behavior for auth, creation, and visibility rules.
 */
class ChatRoomApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify chat-room endpoints reject unauthenticated requests.
     *
     * Logic:
     * 1) Call index endpoint without auth and assert 401 envelope.
     * 2) Call store endpoint without auth and assert 401 envelope.
     *
     * @return void
     */
    public function test_chat_room_endpoints_require_authentication(): void
    {
        $this->getJson('/api/chat-rooms')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->postJson('/api/chat-rooms', [
            'name' => 'Team Room',
            'user_ids' => [1],
        ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    /**
     * Verify an authenticated user can create a room with selected participants.
     *
     * Logic:
     * 1) Create creator and two participant users.
     * 2) Send create-room request as authenticated creator.
     * 3) Assert success payload and creator-only initial membership.
     * 4) Assert room row, creator membership, and pending invites were persisted.
     *
     * @return void
     */
    public function test_authenticated_user_can_create_chat_room_with_selected_users(): void
    {
        $creator = User::factory()->create(['name' => 'Creator']);
        $userA = User::factory()->create(['name' => 'User A']);
        $userB = User::factory()->create(['name' => 'User B']);

        Sanctum::actingAs($creator);

        $response = $this->postJson('/api/chat-rooms', [
            'name' => 'Project Alpha',
            'user_ids' => [$userA->id, $userB->id],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Chat room created successfully.')
            ->assertJsonPath('data.chat_room.name', 'Project Alpha')
            ->assertJsonPath('data.chat_room.created_by_user_id', $creator->id)
            ->assertJsonCount(1, 'data.chat_room.users');

        /** @var ChatRoom $chatRoom */
        $chatRoom = ChatRoom::query()->firstOrFail();

        $this->assertDatabaseHas('chat_rooms', [
            'id' => $chatRoom->id,
            'created_by_user_id' => $creator->id,
            'name' => 'Project Alpha',
        ]);

        $this->assertDatabaseHas('chat_room_user', [
            'chat_room_id' => $chatRoom->id,
            'user_id' => $creator->id,
        ]);

        $this->assertDatabaseHas('chat_room_invites', [
            'chat_room_id' => $chatRoom->id,
            'from_user_id' => $creator->id,
            'to_user_id' => $userA->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('chat_room_invites', [
            'chat_room_id' => $chatRoom->id,
            'from_user_id' => $creator->id,
            'to_user_id' => $userB->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Verify room index only returns rooms where auth user is a participant.
     *
     * Logic:
     * 1) Create one room that includes authenticated user.
     * 2) Create one room that excludes authenticated user.
     * 3) Request room index as authenticated user.
     * 4) Assert only visible room is returned.
     *
     * @return void
     */
    public function test_chat_room_index_returns_only_rooms_where_authenticated_user_is_participant(): void
    {
        $authUser = User::factory()->create();
        $memberUser = User::factory()->create();
        $otherUser = User::factory()->create();

        /** @var ChatRoom $visibleRoom */
        $visibleRoom = ChatRoom::query()->create([
            'created_by_user_id' => $memberUser->id,
            'name' => 'Visible Room',
        ]);

        $visibleRoom->users()->sync([$authUser->id, $memberUser->id]);

        /** @var ChatRoom $hiddenRoom */
        $hiddenRoom = ChatRoom::query()->create([
            'created_by_user_id' => $otherUser->id,
            'name' => 'Hidden Room',
        ]);

        $hiddenRoom->users()->sync([$memberUser->id, $otherUser->id]);

        Sanctum::actingAs($authUser);

        $response = $this->getJson('/api/chat-rooms');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Chat rooms retrieved successfully.')
            ->assertJsonCount(1, 'data.rooms')
            ->assertJsonPath('data.rooms.0.name', 'Visible Room');
    }

    /**
     * Verify invitee can list and accept pending chat-room invites.
     *
     * Logic:
     * 1) Create creator, invitee, room, and one pending invite row.
     * 2) Authenticate as invitee and assert pending invite appears in list API.
     * 3) Accept invite through respond endpoint.
     * 4) Assert invite status is updated and invitee is attached to room membership.
     *
     * @return void
     */
    public function test_invitee_can_accept_room_invite_and_join_room(): void
    {
        $creator = User::factory()->create(['name' => 'Creator']);
        $invitee = User::factory()->create(['name' => 'Invitee']);

        /** @var ChatRoom $chatRoom */
        $chatRoom = ChatRoom::query()->create([
            'created_by_user_id' => $creator->id,
            'name' => 'Invite Room',
        ]);

        $chatRoom->users()->sync([$creator->id]);

        /** @var ChatRoomInvite $invite */
        $invite = ChatRoomInvite::query()->create([
            'chat_room_id' => $chatRoom->id,
            'from_user_id' => $creator->id,
            'to_user_id' => $invitee->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($invitee);

        $this->getJson('/api/chat-rooms/invites')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.invites')
            ->assertJsonPath('data.invites.0.id', $invite->id)
            ->assertJsonPath('data.invites.0.chat_room_name', 'Invite Room');

        $this->postJson("/api/chat-rooms/invites/{$invite->id}/respond", [
            'action' => 'accept',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.invite.status', 'accepted')
            ->assertJsonPath('data.chat_room.id', $chatRoom->id);

        $this->assertDatabaseHas('chat_room_user', [
            'chat_room_id' => $chatRoom->id,
            'user_id' => $invitee->id,
        ]);
    }

}
