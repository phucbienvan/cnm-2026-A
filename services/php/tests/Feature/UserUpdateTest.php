<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_name_can_be_updated(): void
    {
        $user = User::factory()->create(['name' => 'Tên cũ']);

        $response = $this->putJson('/api/users/'.$user->id, [
            'name' => 'Tên mới',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Tên mới');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Tên mới',
        ]);
    }

    public function test_user_name_is_required(): void
    {
        $user = User::factory()->create();

        $this->putJson('/api/users/'.$user->id, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }
}