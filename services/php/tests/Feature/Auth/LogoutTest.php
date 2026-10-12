<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_the_current_access_token(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();

        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->postJson('/api/logout', [], [
            'Authorization' => 'Bearer ' . $token,
        ])->assertOk()
            ->assertJson(['message' => 'Logged out successfully']);

        $this->getJson('/api/users', [
            'Authorization' => 'Bearer ' . $token,
        ])->assertUnauthorized();
    }
}
