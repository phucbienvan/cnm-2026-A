<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_the_current_access_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->postJson('/api/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJson(['message' => 'Logged out successfully']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        Auth::forgetGuards();

        $this->getJson('/api/users', [
            'Authorization' => 'Bearer '.$token,
        ])->assertUnauthorized();
    }

    public function test_logout_requires_a_valid_access_token(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized();

        $this->postJson('/api/logout', [], [
            'Authorization' => 'Bearer invalid-token',
        ])->assertUnauthorized();
    }

    public function test_logout_keeps_other_access_tokens_active(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('current-session')->plainTextToken;
        $otherToken = $user->createToken('other-session')->plainTextToken;

        $this->postJson('/api/logout', [], [
            'Authorization' => 'Bearer '.$currentToken,
        ])->assertOk();

        $this->getJson('/api/users', [
            'Authorization' => 'Bearer '.$otherToken,
        ])->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_login_issues_a_new_usable_access_token(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonStructure(['access_token']);

        $this->getJson('/api/users', [
            'Authorization' => 'Bearer '.$response->json('access_token'),
        ])->assertOk()
            ->assertJsonPath('id', $user->id);
    }
}
