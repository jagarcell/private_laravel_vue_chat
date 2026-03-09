<?php

namespace Tests\Feature\Api;

use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verifies room-scoped chat message API behavior.
 */
class ChatRoomMessageApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify room participant can send and retrieve room messages.
     *
     * Logic:
     * 1) Create room with two participants.
     * 2) Send room message as one participant.
     * 3) Fetch room conversation as the other participant.
     * 4) Assert persisted payload is room-scoped and visible to participants.
     *
     * @return void
     */
    public function test_room_participant_can_send_and_retrieve_room_messages(): void
    {
        $creator = User::factory()->create(['name' => 'Creator']);
        $participant = User::factory()->create(['name' => 'Participant']);

        /** @var ChatRoom $chatRoom */
        $chatRoom = ChatRoom::query()->create([
            'created_by_user_id' => $creator->id,
            'name' => 'Room Alpha',
        ]);

        $chatRoom->users()->sync([$creator->id, $participant->id]);

        Sanctum::actingAs($creator);

        $this->postJson("/api/chat-rooms/{$chatRoom->id}/messages", [
            'message' => 'Hello room',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.chat_message.chat_room_id', $chatRoom->id)
            ->assertJsonPath('data.chat_message.message', 'Hello room');

        Sanctum::actingAs($participant);

        $this->getJson("/api/chat-rooms/{$chatRoom->id}/messages")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.chat_room_id', $chatRoom->id)
            ->assertJsonPath('data.messages.0.message', 'Hello room');
    }
}
