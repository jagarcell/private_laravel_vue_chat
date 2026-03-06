<?php

namespace Tests\Feature\Api;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatMessageApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that authenticated users can send direct messages to online users.
     *
     * Logic:
     * 1) Authenticate sender via Sanctum.
     * 2) Configure database session driver and mark recipient online.
     * 3) Call API and assert success envelope.
     * 4) Assert message broadcast event is dispatched.
     *
     * @return void
     */
    public function test_authenticated_user_can_send_chat_message_to_online_user(): void
    {
        Event::fake([ChatMessageSent::class]);

        $fromUser = User::factory()->create();
        $toUser = User::factory()->create();

        Sanctum::actingAs($fromUser);

        Config::set('session.driver', 'database');
        Config::set('session.lifetime', 120);

        $this->createSessionsTableIfNeeded();

        $this->markUserAsOnline($toUser->id);

        $response = $this->postJson('/api/chat-message/send', [
            'to_user_id' => $toUser->id,
            'message' => 'Hello from test',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Message sent.')
            ->assertJsonPath('data.chat_message.to_user_id', $toUser->id)
            ->assertJsonPath('data.chat_message.message', 'Hello from test');

        Event::assertDispatched(ChatMessageSent::class, function (ChatMessageSent $event) use ($fromUser, $toUser): bool {
            return $event->from_user_id === $fromUser->id
                && $event->to_user_id === $toUser->id
                && $event->message === 'Hello from test';
        });

        $this->assertDatabaseHas('chat_messages', [
            'from_user_id' => $fromUser->id,
            'to_user_id' => $toUser->id,
            'message' => 'Hello from test',
        ]);

        $this->assertSame(1, ChatMessage::query()->count());
    }

    /**
     * Verify that message sending is rejected when target user is offline.
     *
     * Logic:
     * 1) Authenticate sender via Sanctum.
     * 2) Configure database session driver without an active recipient session.
     * 3) Call API and assert 422 error envelope.
     * 4) Assert no message broadcast event is dispatched.
     *
     * @return void
     */
    public function test_authenticated_user_cannot_send_chat_message_to_offline_user(): void
    {
        Event::fake([ChatMessageSent::class]);

        $fromUser = User::factory()->create();
        $toUser = User::factory()->create();

        Sanctum::actingAs($fromUser);

        Config::set('session.driver', 'database');
        Config::set('session.lifetime', 120);

        $this->createSessionsTableIfNeeded();

        $response = $this->postJson('/api/chat-message/send', [
            'to_user_id' => $toUser->id,
            'message' => 'This should fail',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You can only send messages to online users.')
            ->assertJsonPath('data', null);

        Event::assertNotDispatched(ChatMessageSent::class);
    }

    /**
     * Ensure the sessions table exists for online-status checks in tests.
     *
     * Logic:
     * 1) Return early when table already exists.
     * 2) Create minimal schema matching database session driver expectations.
     *
     * @return void
     */
    private function createSessionsTableIfNeeded(): void
    {
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Insert an active session row for a user to simulate online presence.
     *
     * Logic:
     * 1) Compute a timestamp within configured session lifetime.
     * 2) Insert one sessions record for target user.
     *
     * @param  int  $userId
     * @return void
     */
    private function markUserAsOnline(int $userId): void
    {
        $minimumLastActivity = now()
            ->subMinutes((int) config('session.lifetime', 120))
            ->addMinute()
            ->getTimestamp();

        DB::table('sessions')->insert([
            'id' => 'session-'.$userId,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('payload'),
            'last_activity' => $minimumLastActivity,
        ]);
    }
}
