<?php

namespace Tests\Feature\Api;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatConversationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_conversation_endpoints_require_authentication(): void
    {
        $otherUser = User::factory()->create();

        $this->getJson("/api/chat-messages/conversation/{$otherUser->id}")
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->getJson('/api/chat-messages/unread-counts')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->postJson("/api/chat-messages/conversation/{$otherUser->id}/read")
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    /**
     * Verify that conversation endpoint returns persisted messages in both directions.
     *
     * @return void
     */
    public function test_authenticated_user_can_fetch_conversation_history(): void
    {
        $authenticatedUser = User::factory()->create();
        $otherUser = User::factory()->create(['name' => 'Other User']);
        $thirdUser = User::factory()->create();

        ChatMessage::query()->create([
            'from_user_id' => $authenticatedUser->id,
            'to_user_id' => $otherUser->id,
            'message' => 'hello there',
        ]);

        ChatMessage::query()->create([
            'from_user_id' => $otherUser->id,
            'to_user_id' => $authenticatedUser->id,
            'message' => 'general kenobi',
        ]);

        ChatMessage::query()->create([
            'from_user_id' => $thirdUser->id,
            'to_user_id' => $authenticatedUser->id,
            'message' => 'ignore me',
        ]);

        Sanctum::actingAs($authenticatedUser);

        $response = $this->getJson("/api/chat-messages/conversation/{$otherUser->id}");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Conversation retrieved successfully.')
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonPath('data.messages.0.message', 'hello there')
            ->assertJsonPath('data.messages.0.is_mine', true)
            ->assertJsonPath('data.messages.1.message', 'general kenobi')
            ->assertJsonPath('data.messages.1.is_mine', false)
            ->assertJsonPath('data.messages.1.from_user_name', 'Other User');
    }

    /**
     * Verify unread counts endpoint and mark-read endpoint integration.
     *
     * @return void
     */
    public function test_authenticated_user_can_get_unread_counts_and_mark_conversation_read(): void
    {
        $authenticatedUser = User::factory()->create();
        $senderA = User::factory()->create();
        $senderB = User::factory()->create();

        ChatMessage::query()->create([
            'from_user_id' => $senderA->id,
            'to_user_id' => $authenticatedUser->id,
            'message' => 'a-1',
            'read_at' => null,
        ]);

        ChatMessage::query()->create([
            'from_user_id' => $senderA->id,
            'to_user_id' => $authenticatedUser->id,
            'message' => 'a-2',
            'read_at' => null,
        ]);

        ChatMessage::query()->create([
            'from_user_id' => $senderB->id,
            'to_user_id' => $authenticatedUser->id,
            'message' => 'b-1',
            'read_at' => null,
        ]);

        Sanctum::actingAs($authenticatedUser);

        $countsResponse = $this->getJson('/api/chat-messages/unread-counts');

        $countsResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Unread message counts retrieved successfully.')
            ->assertJsonFragment([
                'user_id' => $senderA->id,
                'unread_count' => 2,
            ])
            ->assertJsonFragment([
                'user_id' => $senderB->id,
                'unread_count' => 1,
            ]);

        $markReadResponse = $this->postJson("/api/chat-messages/conversation/{$senderA->id}/read");

        $markReadResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Conversation marked as read.')
            ->assertJsonPath('data.updated_count', 2);

        $this->assertSame(
            0,
            ChatMessage::query()
                ->where('from_user_id', $senderA->id)
                ->where('to_user_id', $authenticatedUser->id)
                ->whereNull('read_at')
                ->count(),
        );
    }

    public function test_conversation_endpoint_validates_limit_query_parameter(): void
    {
        $authenticatedUser = User::factory()->create();
        $otherUser = User::factory()->create();

        Sanctum::actingAs($authenticatedUser);

        $response = $this->getJson("/api/chat-messages/conversation/{$otherUser->id}?limit=0");

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonValidationErrors(['limit']);
    }
}
