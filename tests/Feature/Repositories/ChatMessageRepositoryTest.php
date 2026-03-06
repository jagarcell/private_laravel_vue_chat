<?php

namespace Tests\Feature\Repositories;

use App\Models\ChatMessage;
use App\Models\User;
use App\Repositories\Chat\ChatMessageRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatMessageRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_message_with_sender_relation_loaded(): void
    {
        $sender = User::factory()->create(['name' => 'Sender']);
        $recipient = User::factory()->create();

        /** @var ChatMessageRepository $repository */
        $repository = app(ChatMessageRepository::class);

        $message = $repository->create($sender, $recipient->id, 'hello');

        $this->assertSame($sender->id, $message->from_user_id);
        $this->assertSame($recipient->id, $message->to_user_id);
        $this->assertSame('hello', $message->message);
        $this->assertNull($message->read_at);
        $this->assertTrue($message->relationLoaded('fromUser'));
        $this->assertSame('Sender', $message->fromUser?->name);
    }

    public function test_it_returns_conversation_messages_for_only_the_two_participants(): void
    {
        $authUser = User::factory()->create(['name' => 'Auth']);
        $otherUser = User::factory()->create(['name' => 'Other']);
        $thirdUser = User::factory()->create(['name' => 'Third']);

        ChatMessage::query()->create([
            'from_user_id' => $authUser->id,
            'to_user_id' => $otherUser->id,
            'message' => 'auth -> other',
        ]);

        ChatMessage::query()->create([
            'from_user_id' => $otherUser->id,
            'to_user_id' => $authUser->id,
            'message' => 'other -> auth',
        ]);

        ChatMessage::query()->create([
            'from_user_id' => $thirdUser->id,
            'to_user_id' => $authUser->id,
            'message' => 'third -> auth',
        ]);

        /** @var ChatMessageRepository $repository */
        $repository = app(ChatMessageRepository::class);

        $conversation = $repository->conversation($authUser, $otherUser, 200);

        $this->assertCount(2, $conversation);
        $this->assertSame('auth -> other', $conversation[0]->message);
        $this->assertSame('other -> auth', $conversation[1]->message);
        $this->assertTrue($conversation->every(fn (ChatMessage $message): bool => $message->relationLoaded('fromUser')));
    }

    public function test_it_returns_unread_counts_grouped_by_sender(): void
    {
        $authUser = User::factory()->create();
        $senderA = User::factory()->create();
        $senderB = User::factory()->create();

        ChatMessage::query()->create([
            'from_user_id' => $senderA->id,
            'to_user_id' => $authUser->id,
            'message' => 'a1',
            'read_at' => null,
        ]);

        ChatMessage::query()->create([
            'from_user_id' => $senderA->id,
            'to_user_id' => $authUser->id,
            'message' => 'a2',
            'read_at' => null,
        ]);

        ChatMessage::query()->create([
            'from_user_id' => $senderB->id,
            'to_user_id' => $authUser->id,
            'message' => 'b1',
            'read_at' => null,
        ]);

        ChatMessage::query()->create([
            'from_user_id' => $senderB->id,
            'to_user_id' => $authUser->id,
            'message' => 'b2-read',
            'read_at' => now(),
        ]);

        /** @var ChatMessageRepository $repository */
        $repository = app(ChatMessageRepository::class);

        $counts = collect($repository->unreadCounts($authUser))->keyBy('user_id');

        $this->assertSame(2, $counts[$senderA->id]['unread_count']);
        $this->assertSame(1, $counts[$senderB->id]['unread_count']);
    }

    public function test_it_marks_only_targeted_unread_messages_as_read(): void
    {
        $authUser = User::factory()->create();
        $targetSender = User::factory()->create();
        $otherSender = User::factory()->create();

        ChatMessage::query()->create([
            'from_user_id' => $targetSender->id,
            'to_user_id' => $authUser->id,
            'message' => 'target-unread-1',
            'read_at' => null,
        ]);

        ChatMessage::query()->create([
            'from_user_id' => $targetSender->id,
            'to_user_id' => $authUser->id,
            'message' => 'target-unread-2',
            'read_at' => null,
        ]);

        ChatMessage::query()->create([
            'from_user_id' => $otherSender->id,
            'to_user_id' => $authUser->id,
            'message' => 'other-unread',
            'read_at' => null,
        ]);

        /** @var ChatMessageRepository $repository */
        $repository = app(ChatMessageRepository::class);

        $updated = $repository->markConversationAsRead($authUser, $targetSender);

        $this->assertSame(2, $updated);

        $this->assertSame(
            0,
            ChatMessage::query()
                ->where('from_user_id', $targetSender->id)
                ->where('to_user_id', $authUser->id)
                ->whereNull('read_at')
                ->count(),
        );

        $this->assertSame(
            1,
            ChatMessage::query()
                ->where('from_user_id', $otherSender->id)
                ->where('to_user_id', $authUser->id)
                ->whereNull('read_at')
                ->count(),
        );
    }
}
