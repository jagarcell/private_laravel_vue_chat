<?php

namespace Tests\Feature\Repositories;

use App\Repositories\Users\OnlineUserRepository;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OnlineUserRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_put_and_all_normalize_and_deduplicate_ids(): void
    {
        /** @var OnlineUserRepository $repository */
        $repository = app(OnlineUserRepository::class);

        $repository->put([10, '10', 20, 20, 30]);

        $this->assertSame([10, 20, 30], $repository->all()->all());
    }

    public function test_mark_online_and_offline_update_tracked_set(): void
    {
        /** @var OnlineUserRepository $repository */
        $repository = app(OnlineUserRepository::class);

        $repository->markOnline(100);
        $repository->markOnline(200);

        $this->assertSame([100, 200], $repository->all()->all());

        $repository->markOffline(100);

        $this->assertSame([200], $repository->all()->all());
    }
}
