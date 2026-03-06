<?php

namespace Tests\Feature\Repositories;

use App\Models\User;
use App\Repositories\Users\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_returns_users_ordered_by_name(): void
    {
        User::factory()->create(['name' => 'Zed']);
        User::factory()->create(['name' => 'Charlie']);
        User::factory()->create(['name' => 'Alice']);
        User::factory()->create(['name' => 'Bob']);

        /** @var UserRepository $repository */
        $repository = app(UserRepository::class);

        $users = $repository->directory();

        $this->assertCount(4, $users);
        $this->assertSame(['Alice', 'Bob', 'Charlie', 'Zed'], $users->pluck('name')->all());
    }

    public function test_directory_applies_search_filter(): void
    {
        User::factory()->create(['name' => 'Excluded']);
        User::factory()->create(['name' => 'Alice']);
        User::factory()->create(['name' => 'Bob']);
        User::factory()->create(['name' => 'Alicia']);

        /** @var UserRepository $repository */
        $repository = app(UserRepository::class);

        $users = $repository->directory(['search' => 'ali']);

        $this->assertCount(2, $users);
        $this->assertSame(['Alice', 'Alicia'], $users->pluck('name')->all());
    }
}
