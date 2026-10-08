<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function beforeRefreshingDatabase(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
    }

    public function test_logout_revokes_only_the_current_token_and_prevents_reuse(): void
    {
        $user = User::factory()->create();
        $otherToken = $user->createToken('other-device');
        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();
        $token = $login->json('access_token');

        $this->withToken($token)->getJson('/api/users')->assertOk()->assertJsonPath('id', $user->id);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/logout')->assertOk()->assertJsonPath('message', 'Logged out successfully');

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->accessToken->id]);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/users')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/logout')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken->plainTextToken)->getJson('/api/users')->assertOk();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized();
        $this->withToken('invalid-token')->postJson('/api/logout')->assertUnauthorized();
    }

    public function test_logout_does_not_accept_get_requests(): void
    {
        $this->getJson('/api/logout')->assertStatus(405);
    }
}
