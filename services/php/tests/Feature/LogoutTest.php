<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        // Tách dữ liệu kiểm thử khỏi cơ sở dữ liệu của ứng dụng.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
        ]);
    }

    public function test_user_can_logout_with_token_from_login(): void
    {
        $user = User::factory()->create();
        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $token = $login->json('access_token');
        $this->assertNotEmpty($token);

        $this->withToken($token)->postJson('/api/logout')
            ->assertOk()
            ->assertExactJson(['message' => 'Đăng xuất thành công']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        // Xác thực lại ở lần gọi tiếp theo, không dùng người dùng đã lưu trong guard.
        Auth::forgetGuards();

        $this->withToken($token)->postJson('/api/logout')->assertUnauthorized();
    }

    public function test_logout_keeps_other_tokens_active(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('current-device');
        $otherToken = $user->createToken('other-device');
        $anotherUser = User::factory()->create();
        $anotherUsersToken = $anotherUser->createToken('another-user');

        $this->withToken($currentToken->plainTextToken)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $currentToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $anotherUsersToken->accessToken->id]);

        Auth::forgetGuards();

        $this->withToken($otherToken->plainTextToken)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $anotherUsersToken->accessToken->id]);
    }

    public function test_logout_rejects_missing_or_invalid_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('valid-token');

        $this->postJson('/api/logout')->assertUnauthorized();
        Auth::forgetGuards();
        $this->withToken('invalid-token')->postJson('/api/logout')->assertUnauthorized();

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_get_request_cannot_revoke_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('valid-token');

        $this->withToken($token->plainTextToken)->getJson('/api/logout')->assertStatus(405);

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }
}
