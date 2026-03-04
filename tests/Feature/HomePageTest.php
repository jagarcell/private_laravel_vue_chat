<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_web_route_redirects_to_home_page(): void
    {
        $response = $this->get('/this-route-does-not-exist');

        $response->assertRedirect('/');
    }

    /**
     * A basic test example.
     */
    public function test_guest_is_redirected_to_login_from_chat_home(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_chat_home(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/');

        $response->assertOk();
    }
}
