<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_deletes_the_access_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logged out successfully']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_only_deletes_the_current_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('auth_token')->plainTextToken;
        $user->createToken('other_device');

        $this->withToken($current)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'other_device']);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized();
    }
}
