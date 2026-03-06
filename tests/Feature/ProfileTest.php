<?php

namespace Tests\Feature;

use App\Events\ChatRequestMessage;
use App\Models\User;
use App\Repositories\Chat\ActiveChatConnectionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_requires_authentication(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect('/login');
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_profile_email_must_be_unique_for_other_users(): void
    {
        $currentUser = User::factory()->create();
        $existingUser = User::factory()->create();

        $response = $this
            ->actingAs($currentUser)
            ->from('/profile')
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $existingUser->email,
            ]);

        $response
            ->assertRedirect('/profile')
            ->assertSessionHasErrors('email');
    }

    public function test_profile_name_is_required_when_updating_profile(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => '',
                'email' => $user->email,
            ]);

        $response
            ->assertRedirect('/profile')
            ->assertSessionHasErrors('name');
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_account_deletion_closes_active_chats_and_notifies_peers(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $peer = User::factory()->create();

        /** @var ActiveChatConnectionRepository $connectionRepository */
        $connectionRepository = app(ActiveChatConnectionRepository::class);
        $connectionRepository->connectBidirectional($user->id, $peer->id);

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        Event::assertDispatched(ChatRequestMessage::class, function (ChatRequestMessage $event) use ($user, $peer): bool {
            return $event->from_user_id === $user->id
                && $event->to_user_id === $peer->id
                && $event->type === 'closed';
        });

        $this->assertSame([], $connectionRepository->connectedUserIds($user->id)->all());
        $this->assertSame([], $connectionRepository->connectedUserIds($peer->id)->all());
    }
}
