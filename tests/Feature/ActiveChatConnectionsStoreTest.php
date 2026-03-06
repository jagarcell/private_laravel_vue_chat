<?php

namespace Tests\Feature;

use App\Support\ActiveChatConnectionsStore;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ActiveChatConnectionsStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_connect_bidirectional_stores_peers_for_both_users(): void
    {
        /** @var ActiveChatConnectionsStore $store */
        $store = app(ActiveChatConnectionsStore::class);

        $store->connectBidirectional(10, 20);

        $this->assertSame([20], $store->connectedUserIds(10)->all());
        $this->assertSame([10], $store->connectedUserIds(20)->all());
    }

    public function test_disconnect_bidirectional_removes_peers_for_both_users(): void
    {
        /** @var ActiveChatConnectionsStore $store */
        $store = app(ActiveChatConnectionsStore::class);

        $store->connectBidirectional(10, 20);
        $store->disconnectBidirectional(10, 20);

        $this->assertSame([], $store->connectedUserIds(10)->all());
        $this->assertSame([], $store->connectedUserIds(20)->all());
    }

    public function test_disconnect_all_for_user_clears_user_and_peer_references(): void
    {
        /** @var ActiveChatConnectionsStore $store */
        $store = app(ActiveChatConnectionsStore::class);

        $store->connectBidirectional(10, 20);
        $store->connectBidirectional(10, 30);

        $disconnectedPeers = $store->disconnectAllForUser(10);
        sort($disconnectedPeers);

        $this->assertSame([20, 30], $disconnectedPeers);
        $this->assertSame([], $store->connectedUserIds(10)->all());
        $this->assertSame([], $store->connectedUserIds(20)->all());
        $this->assertSame([], $store->connectedUserIds(30)->all());
    }
}
