<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsersApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_api_route_returns_consistent_not_found_envelope(): void
    {
        $response = $this->getJson('/api/unknown-route');

        $response
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Resource not found.')
            ->assertJsonPath('data', null)
            ->assertJsonMissingPath('errors');
    }

    public function test_users_api_requires_authentication(): void
    {
        $response = $this->getJson('/api/users');

        $response
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('data', null)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'errors' => [
                    'auth',
                ],
            ]);
    }

    public function test_authenticated_user_can_list_users_with_online_indicator(): void
    {
        $authenticatedUser = User::factory()->create([
            'name' => 'Authenticated User',
            'email' => 'auth@example.com',
        ]);

        User::factory()->create([
            'name' => 'Alice User',
            'email' => 'alice@example.com',
        ]);

        User::factory()->create([
            'name' => 'Bob User',
            'email' => 'bob@example.com',
        ]);

        Sanctum::actingAs($authenticatedUser);

        $response = $this->getJson('/api/users');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Users retrieved successfully.')
            ->assertJsonCount(3, 'data.users')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'users' => [
                        '*' => ['id', 'name', 'email', 'is_online'],
                    ],
                ],
            ]);

        $users = collect($response->json('data.users'));

        $this->assertSame(1, $users->where('is_online', true)->count());
        $this->assertTrue((bool) $users->firstWhere('id', $authenticatedUser->id)['is_online']);
    }

    public function test_users_api_validates_search_parameter(): void
    {
        $authenticatedUser = User::factory()->create();

        Sanctum::actingAs($authenticatedUser);

        $response = $this->getJson('/api/users?search[]='.(urlencode('invalid')));

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('data', null)
            ->assertJsonValidationErrors(['search']);
    }
}
