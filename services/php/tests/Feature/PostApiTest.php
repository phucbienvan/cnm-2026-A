<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_posts(): void
    {
        Post::create([
            'title' => 'Bài viết mẫu 1',
            'content' => 'Nội dung bài viết 1',
        ]);

        $response = $this->getJson('/api/posts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'content'],
                ],
                'message',
            ])
            ->assertJson([
                'message' => 'Lấy bài viết thành công',
            ]);
    }

    public function test_can_create_post(): void
    {
        $payload = [
            'title' => 'Bài viết mới',
            'content' => 'Nội dung bài viết mới',
        ];

        $response = $this->postJson('/api/posts', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Thêm bài viết thành công',
                'data' => [
                    'title' => 'Bài viết mới',
                    'content' => 'Nội dung bài viết mới',
                ],
            ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'Bài viết mới',
        ]);
    }

    public function test_can_show_post(): void
    {
        $post = Post::create([
            'title' => 'Chi tiết bài viết',
            'content' => 'Nội dung chi tiết',
        ]);

        $response = $this->getJson("/api/posts/{$post->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Lấy bài viết thành công',
                'data' => [
                    'id' => $post->id,
                    'title' => 'Chi tiết bài viết',
                ],
            ]);
    }

    public function test_can_update_post(): void
    {
        $post = Post::create([
            'title' => 'Tiêu đề cũ',
            'content' => 'Nội dung cũ',
        ]);

        $response = $this->putJson("/api/posts/{$post->id}", [
            'title' => 'Tiêu đề mới',
            'content' => 'Nội dung mới',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Cập nhật bài viết thành công',
                'data' => [
                    'id' => $post->id,
                    'title' => 'Tiêu đề mới',
                    'content' => 'Nội dung mới',
                ],
            ]);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Tiêu đề mới',
        ]);
    }

    public function test_can_delete_post(): void
    {
        $post = Post::create([
            'title' => 'Bài viết cần xóa',
            'content' => 'Nội dung cần xóa',
        ]);

        $response = $this->deleteJson("/api/posts/{$post->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Xóa bài viết thành công',
            ]);

        $this->assertSoftDeleted('posts', [
            'id' => $post->id,
        ]);
    }

    public function test_returns_404_when_post_not_found(): void
    {
        $response = $this->getJson('/api/posts/99999');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Không tìm thấy bài viết',
            ]);
    }

    public function test_can_get_user_info(): void
    {
        $user = User::factory()->create([
            'name' => 'Nguyễn Văn A',
        ]);

        $response = $this->getJson("/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $user->id,
                'name' => 'Nguyễn Văn A',
                'message' => 'Hello',
            ]);
    }
}