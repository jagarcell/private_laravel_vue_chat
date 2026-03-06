<?php

namespace Tests\Feature\Auth;

use App\Events\ChatRequestMessage;
use App\Events\UserOnlineStatusChanged;
use App\Models\User;
use App\Support\ActiveChatConnectionsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_after_too_many_failed_attempts(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        Event::fake();

        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        Event::assertDispatched(UserOnlineStatusChanged::class, function (UserOnlineStatusChanged $event) use ($user): bool {
            return $event->user_id === $user->id && $event->is_online === true;
        });

        $this->assertAuthenticated();
        $response->assertRedirect('/');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        Event::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        Event::assertDispatched(UserOnlineStatusChanged::class, function (UserOnlineStatusChanged $event) use ($user): bool {
            return $event->user_id === $user->id && $event->is_online === false;
        });

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    /**
     * Verify logout closes active chats and notifies connected peers.
     *
     * Logic:
     * 1) Seed one active connection in chat connection store.
     * 2) Log out connected user.
     * 3) Assert `closed` chat-request message is broadcast to peer.
     *
     * @return void
     */
    public function test_logout_broadcasts_closed_for_active_chat_connections(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $peer = User::factory()->create();

        /** @var ActiveChatConnectionsStore $connectionsStore */
        $connectionsStore = app(ActiveChatConnectionsStore::class);
        $connectionsStore->connectBidirectional($user->id, $peer->id);

        $response = $this->actingAs($user)->post('/logout');

        Event::assertDispatched(ChatRequestMessage::class, function (ChatRequestMessage $event) use ($user, $peer): bool {
            return $event->from_user_id === $user->id
                && $event->to_user_id === $peer->id
                && $event->type === 'closed';
        });

        $this->assertSame([], $connectionsStore->connectedUserIds($user->id)->all());
        $this->assertSame([], $connectionsStore->connectedUserIds($peer->id)->all());

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
