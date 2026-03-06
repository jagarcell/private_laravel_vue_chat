<?php

namespace Tests\Feature\Repositories;

use App\Repositories\Chat\ActiveChatConnectionRepository;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ActiveChatConnectionRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_connect_bidirectional_stores_peers_for_both_users(): void
    {
        /** @var ActiveChatConnectionRepository $repository */
        $repository = app(ActiveChatConnectionRepository::class);

        $repository->connectBidirectional(10, 20);

        $this->assertSame([20], $repository->connectedUserIds(10)->all());
        $this->assertSame([10], $repository->connectedUserIds(20)->all());
    }

    public function test_disconnect_bidirectional_removes_peers_for_both_users(): void
    {
        /** @var ActiveChatConnectionRepository $repository */
        $repository = app(ActiveChatConnectionRepository::class);

        $repository->connectBidirectional(10, 20);
        $repository->disconnectBidirectional(10, 20);

        $this->assertSame([], $repository->connectedUserIds(10)->all());
        $this->assertSame([], $repository->connectedUserIds(20)->all());
    }

    public function test_disconnect_all_for_user_returns_disconnected_peer_ids(): void
    {
        /** @var ActiveChatConnectionRepository $repository */
        $repository = app(ActiveChatConnectionRepository::class);

        $repository->connectBidirectional(10, 20);
        $repository->connectBidirectional(10, 30);

        $disconnectedPeers = $repository->disconnectAllForUser(10);
        sort($disconnectedPeers);

        $this->assertSame([20, 30], $disconnectedPeers);
        $this->assertSame([], $repository->connectedUserIds(10)->all());
        $this->assertSame([], $repository->connectedUserIds(20)->all());
        $this->assertSame([], $repository->connectedUserIds(30)->all());
    }
}
