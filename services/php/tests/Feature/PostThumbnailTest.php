<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PostThumbnailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function signIn(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_can_create_post_with_thumbnail(): void
    {
        $this->signIn();
        $file = UploadedFile::fake()->image('thumbnail.jpg');

        $response = $this->post('/api/posts', [
            'title' => 'Bài viết có ảnh',
            'content' => 'Nội dung bài viết',
            'thumbnail' => $file,
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath(
            'data.thumbnail',
            Storage::disk('public')->url('post-thumbnails/'.$file->hashName())
        );
        $this->assertDatabaseHas('posts', [
            'title' => 'Bài viết có ảnh',
            'thumbnail' => 'post-thumbnails/'.$file->hashName(),
        ]);
        Storage::disk('public')->assertExists('post-thumbnails/'.$file->hashName());
    }

    public function test_can_create_post_without_thumbnail(): void
    {
        $this->signIn();
        $this->postJson('/api/posts', [
            'title' => 'Bài viết không ảnh',
            'content' => 'Nội dung bài viết',
        ])->assertOk()->assertJsonPath('data.thumbnail', null);

        $this->assertDatabaseHas('posts', ['thumbnail' => null]);
    }

    public function test_update_replaces_old_thumbnail(): void
    {
        $user = $this->signIn();
        $oldPath = 'post-thumbnails/old.jpg';
        Storage::disk('public')->put($oldPath, 'old image');
        $post = Post::factory()->create(['user_id' => $user->id, 'thumbnail' => $oldPath]);
        $newFile = UploadedFile::fake()->image('new.png');

        $this->post('/api/posts/'.$post->id, [
            '_method' => 'PUT',
            'title' => 'Bài viết đã sửa',
            'content' => 'Nội dung đã sửa',
            'thumbnail' => $newFile,
        ], ['Accept' => 'application/json'])->assertOk();

        $newPath = 'post-thumbnails/'.$newFile->hashName();
        $this->assertSame($newPath, $post->fresh()->thumbnail);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_delete_post_removes_thumbnail(): void
    {
        $user = $this->signIn();
        $path = 'post-thumbnails/delete-me.jpg';
        Storage::disk('public')->put($path, 'image');
        $post = Post::factory()->create(['user_id' => $user->id, 'thumbnail' => $path]);

        $this->deleteJson('/api/posts/'.$post->id)->assertOk();

        Storage::disk('public')->assertMissing($path);
        $this->assertSoftDeleted($post);
    }

    public function test_thumbnail_must_be_a_supported_image_under_two_megabytes(): void
    {
        $this->signIn();
        $response = $this->postJson('/api/posts', [
            'title' => 'Bài viết lỗi',
            'content' => 'Nội dung',
            'thumbnail' => UploadedFile::fake()->create('document.pdf', 100),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('thumbnail');
    }
}
