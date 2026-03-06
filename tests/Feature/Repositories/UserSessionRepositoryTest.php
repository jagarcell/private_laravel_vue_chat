<?php

namespace Tests\Feature\Repositories;

use App\Repositories\Users\UserSessionRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserSessionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_resolve_active_users_reflects_driver_and_table_availability(): void
    {
        /** @var UserSessionRepository $repository */
        $repository = app(UserSessionRepository::class);

        Config::set('session.driver', 'array');
        $this->assertFalse($repository->canResolveActiveUsers());

        Config::set('session.driver', 'database');

        Schema::dropIfExists('sessions');
        $this->assertFalse($repository->canResolveActiveUsers());

        $this->createSessionsTableIfNeeded();
        $this->assertTrue($repository->canResolveActiveUsers());
    }

    public function test_it_returns_false_when_session_driver_is_not_database(): void
    {
        Config::set('session.driver', 'array');

        /** @var UserSessionRepository $repository */
        $repository = app(UserSessionRepository::class);

        $this->assertFalse($repository->isUserOnline(1));
    }

    public function test_it_returns_false_when_sessions_table_does_not_exist(): void
    {
        Config::set('session.driver', 'database');

        /** @var UserSessionRepository $repository */
        $repository = app(UserSessionRepository::class);

        $this->assertFalse($repository->isUserOnline(1));
    }

    public function test_it_returns_true_only_for_users_with_active_session_within_threshold(): void
    {
        Config::set('session.driver', 'database');
        Config::set('session.lifetime', 120);

        $this->createSessionsTableIfNeeded();

        $activeUserId = 101;
        $inactiveUserId = 202;

        DB::table('sessions')->insert([
            [
                'id' => 'active-session',
                'user_id' => $activeUserId,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => base64_encode('active'),
                'last_activity' => now()->subMinutes(5)->getTimestamp(),
            ],
            [
                'id' => 'inactive-session',
                'user_id' => $inactiveUserId,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => base64_encode('inactive'),
                'last_activity' => now()->subMinutes(500)->getTimestamp(),
            ],
        ]);

        /** @var UserSessionRepository $repository */
        $repository = app(UserSessionRepository::class);

        $this->assertTrue($repository->isUserOnline($activeUserId));
        $this->assertFalse($repository->isUserOnline($inactiveUserId));
        $this->assertFalse($repository->isUserOnline(999999));
    }

    public function test_active_user_ids_returns_unique_active_ids_only(): void
    {
        Config::set('session.driver', 'database');
        Config::set('session.lifetime', 120);

        $this->createSessionsTableIfNeeded();

        DB::table('sessions')->insert([
            [
                'id' => 'active-1',
                'user_id' => 101,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => base64_encode('active-1'),
                'last_activity' => now()->subMinutes(1)->getTimestamp(),
            ],
            [
                'id' => 'active-1-duplicate',
                'user_id' => 101,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => base64_encode('active-1-dup'),
                'last_activity' => now()->subMinutes(2)->getTimestamp(),
            ],
            [
                'id' => 'active-2',
                'user_id' => 202,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => base64_encode('active-2'),
                'last_activity' => now()->subMinutes(3)->getTimestamp(),
            ],
            [
                'id' => 'inactive-old',
                'user_id' => 303,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => base64_encode('inactive'),
                'last_activity' => now()->subMinutes(500)->getTimestamp(),
            ],
            [
                'id' => 'null-user-id',
                'user_id' => null,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'payload' => base64_encode('null-user'),
                'last_activity' => now()->subMinutes(2)->getTimestamp(),
            ],
        ]);

        /** @var UserSessionRepository $repository */
        $repository = app(UserSessionRepository::class);

        $activeUserIds = $repository->activeUserIds()->all();
        sort($activeUserIds);

        $this->assertSame([101, 202], $activeUserIds);
    }

    private function createSessionsTableIfNeeded(): void
    {
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }
}
