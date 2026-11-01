<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostThumbnailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Test create post with thumbnail.
     */
    public function test_can_create_post_with_thumbnail(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('thumbnail.jpg');

        $response = $this->actingAs($user)->postJson('/api/posts', [
            'title' => 'Test Post',
            'content' => 'Test Content',
            'thumbnail' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'title',
                    'content',
                    'image_url',
                    'user_id',
                ]
            ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'content' => 'Test Content',
        ]);

        Storage::disk('public')->assertExists('post-thumbnails/' . $file->hashName());
    }

    /**
     * Test create post without thumbnail.
     */
    public function test_can_create_post_without_thumbnail(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/posts', [
            'title' => 'Test Post',
            'content' => 'Test Content',
        ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'thumbnail' => null,
        ]);
    }

    /**
     * Test update post with new thumbnail.
     */
    public function test_can_update_post_with_new_thumbnail(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        
        $newFile = UploadedFile::fake()->image('new-thumbnail.jpg');

        $response = $this->actingAs($user)->putJson("/api/posts/{$post->id}", [
            'title' => 'Updated Post',
            'content' => 'Updated Content',
            'thumbnail' => $newFile,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Cập nhật bài viết thành công',
            ]);

        Storage::disk('public')->assertExists('post-thumbnails/' . $newFile->hashName());
    }

    /**
     * Test delete post also deletes thumbnail.
     */
    public function test_deleting_post_deletes_thumbnail(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('thumbnail.jpg');
        
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'thumbnail' => 'post-thumbnails/test.jpg',
        ]);

        Storage::disk('public')->put('post-thumbnails/test.jpg', 'content');

        $response = $this->actingAs($user)->deleteJson("/api/posts/{$post->id}");

        $response->assertStatus(200);
        Storage::disk('public')->assertMissing('post-thumbnails/test.jpg');
    }

    /**
     * Test validation for thumbnail upload.
     */
    public function test_thumbnail_validation(): void
    {
        $user = User::factory()->create();

        // Test with non-image file
        $file = UploadedFile::fake()->create('document.pdf', 100);
        
        $response = $this->actingAs($user)->postJson('/api/posts', [
            'title' => 'Test Post',
            'content' => 'Test Content',
            'thumbnail' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['thumbnail']);
    }

    /**
     * Test thumbnail size validation.
     */
    public function test_thumbnail_max_size_validation(): void
    {
        $user = User::factory()->create();

        // Test with file > 2MB
        $file = UploadedFile::fake()->image('large.jpg')->size(3000);
        
        $response = $this->actingAs($user)->postJson('/api/posts', [
            'title' => 'Test Post',
            'content' => 'Test Content',
            'thumbnail' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['thumbnail']);
    }
}
