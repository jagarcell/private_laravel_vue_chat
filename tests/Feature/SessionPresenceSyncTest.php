<?php

namespace Tests\Feature;

use App\Events\UserOnlineStatusChanged;
use App\Models\User;
use App\Support\OnlineUsersStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifies scheduled presence synchronization broadcasts expected offline events.
 */
class SessionPresenceSyncTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Prepare test runtime configuration.
     *
     * Logic:
     * 1) Boot the parent test case.
     * 2) Force the session driver to `database` so session table logic is exercised.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('session.driver', 'database');
    }

    /**
     * Assert that expired tracked users emit an offline presence broadcast.
     *
     * Logic:
     * 1) Fake events to capture broadcast dispatches.
     * 2) Create a user and mark them as tracked-online in cache.
     * 3) Run the presence sync command.
     * 4) Assert `UserOnlineStatusChanged` was dispatched with `is_online=false` for that user.
     *
     * @return void
     */
    public function test_it_broadcasts_offline_when_a_tracked_user_session_has_expired(): void
    {
        Event::fake();

        $user = User::factory()->create();

        app(OnlineUsersStore::class)->put([$user->id]);

        $this->artisan('presence:sync-expired-sessions')->assertSuccessful();

        Event::assertDispatched(UserOnlineStatusChanged::class, function (UserOnlineStatusChanged $event) use ($user): bool {
            return $event->user_id === $user->id && $event->is_online === false;
        });
    }

    /**
     * Assert that users with active sessions are not broadcast as offline.
     *
     * Logic:
     * 1) Fake events to observe dispatch behavior.
     * 2) Create a user and insert an active row in `sessions`.
     * 3) Mark the same user as tracked-online in cache.
     * 4) Run the presence sync command.
     * 5) Assert no offline presence event is dispatched for that user.
     *
     * @return void
     */
    public function test_it_does_not_broadcast_offline_for_users_with_active_sessions(): void
    {
        Event::fake();

        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => base64_encode('test'),
            'last_activity' => now()->getTimestamp(),
        ]);

        app(OnlineUsersStore::class)->put([$user->id]);

        $this->artisan('presence:sync-expired-sessions')->assertSuccessful();

        Event::assertNotDispatched(UserOnlineStatusChanged::class, function (UserOnlineStatusChanged $event) use ($user): bool {
            return $event->user_id === $user->id && $event->is_online === false;
        });
    }
}
