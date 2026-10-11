<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_the_current_access_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');

        $response = $this->withToken($token->plainTextToken)->postJson('/api/logout');

        $response->assertOk()->assertExactJson([
            'message' => 'Logout successfully',
        ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->getKey(),
        ]);
    }
}
