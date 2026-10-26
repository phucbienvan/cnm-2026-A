<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class PostThumbnailTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_a_post_with_a_thumbnail_on_local_public_storage(): void
    {
        Storage::fake('public', ['url' => 'https://example.test/storage']);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/posts', [
            'title' => 'A post with a thumbnail',
            'content' => 'Post content',
            'thumbnail' => $this->thumbnail(),
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $path = $response->json('data.thumbnail');
        $this->assertStringStartsWith('posts/', $path);
        $this->assertStringEndsWith('.png', $path);
        $response->assertJsonPath('data.thumbnail_url', 'https://example.test/storage/'.$path);
        $this->assertDatabaseHas('posts', [
            'id' => $response->json('data.id'),
            'title' => 'A post with a thumbnail',
            'thumbnail' => $path,
            'user_id' => $user->id,
        ]);
        Storage::disk('public')->assertExists($path);
    }

    public function test_creates_a_post_without_a_thumbnail(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/posts', [
            'title' => 'A post without an image',
            'content' => 'Post content',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.thumbnail', null)
            ->assertJsonPath('data.thumbnail_url', null);
        $this->assertDatabaseHas('posts', ['id' => $response->json('data.id'), 'thumbnail' => null]);
        Storage::disk('public')->assertDirectoryEmpty('posts');
    }

    #[TestWith(['document.pdf', 'application/pdf', 100])]
    #[TestWith(['thumbnail.svg', 'image/svg+xml', 100])]
    #[TestWith(['thumbnail.bmp', 'image/bmp', 100])]
    #[TestWith(['thumbnail.png', 'image/png', 2049])]
    public function test_returns_422_for_an_invalid_thumbnail(string $name, string $mimeType, int $kilobytes): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());

        $response = $this->post('/api/posts', [
            'title' => 'Invalid upload',
            'content' => 'Post content',
            'thumbnail' => UploadedFile::fake()->create($name, $kilobytes, $mimeType),
        ], ['Accept' => 'application/json']);

        $response->assertUnprocessable()->assertJsonValidationErrors('thumbnail');
        $this->assertDatabaseCount('posts', 0);
        Storage::disk('public')->assertDirectoryEmpty('posts');
    }

    public function test_returns_401_and_does_not_upload_for_an_unauthenticated_request(): void
    {
        Storage::fake('public');

        $response = $this->post('/api/posts', [
            'title' => 'Unauthorized upload',
            'content' => 'Post content',
            'thumbnail' => $this->thumbnail(),
        ], ['Accept' => 'application/json']);

        $response->assertUnauthorized();
        $this->assertDatabaseCount('posts', 0);
        Storage::disk('public')->assertDirectoryEmpty('posts');
    }

    public function test_replaces_the_thumbnail_using_multipart_post_with_put_method_override(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $post = $this->postWithThumbnail($user);
        Sanctum::actingAs($user);

        $response = $this->post('/api/posts/'.$post->id, [
            '_method' => 'PUT',
            'title' => 'Updated title',
            'content' => 'Updated content',
            'thumbnail' => $this->thumbnail(),
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $path = $response->json('data.thumbnail');
        $this->assertNotSame($post->thumbnail, $path);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated title',
            'content' => 'Updated content',
            'thumbnail' => $path,
        ]);
        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertMissing($post->thumbnail);
    }

    public function test_keeps_the_existing_thumbnail_when_updating_without_an_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $post = $this->postWithThumbnail($user);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/posts/'.$post->id, [
            'title' => 'Updated title',
            'content' => 'Updated content',
        ]);

        $response->assertOk()->assertJsonPath('data.thumbnail', $post->thumbnail);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated title',
            'thumbnail' => $post->thumbnail,
        ]);
        Storage::disk('public')->assertExists($post->thumbnail);
    }

    public function test_returns_403_without_changing_files_when_updating_another_users_post(): void
    {
        Storage::fake('public');
        $post = $this->postWithThumbnail(User::factory()->create());
        Sanctum::actingAs(User::factory()->create());

        $response = $this->put('/api/posts/'.$post->id, [
            'title' => 'Unauthorized update',
            'content' => 'Post content',
            'thumbnail' => $this->thumbnail(),
        ], ['Accept' => 'application/json']);

        $response->assertForbidden();
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => $post->title, 'thumbnail' => $post->thumbnail]);
        $this->assertSame([$post->thumbnail], Storage::disk('public')->allFiles('posts'));
    }

    public function test_returns_422_and_keeps_the_old_image_when_an_update_image_is_invalid(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $post = $this->postWithThumbnail($user);
        Sanctum::actingAs($user);

        $response = $this->put('/api/posts/'.$post->id, [
            'title' => 'Invalid update',
            'content' => 'Post content',
            'thumbnail' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertUnprocessable()->assertJsonValidationErrors('thumbnail');
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => $post->title, 'thumbnail' => $post->thumbnail]);
        $this->assertSame([$post->thumbnail], Storage::disk('public')->allFiles('posts'));
    }

    public function test_removes_the_uploaded_image_when_creating_the_post_fails(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create());
        Event::listen('eloquent.creating: '.Post::class, function (): void {
            throw new RuntimeException('Post creation failed.');
        });

        $response = $this->post('/api/posts', [
            'title' => 'Failed creation',
            'content' => 'Post content',
            'thumbnail' => $this->thumbnail(),
        ], ['Accept' => 'application/json']);

        $response->assertInternalServerError();
        $this->assertDatabaseCount('posts', 0);
        Storage::disk('public')->assertDirectoryEmpty('posts');
    }

    public function test_keeps_the_old_image_and_removes_the_new_image_when_saving_an_update_fails(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $post = $this->postWithThumbnail($user);
        Sanctum::actingAs($user);
        Event::listen('eloquent.updating: '.Post::class, function (): void {
            throw new RuntimeException('Post update failed.');
        });

        $response = $this->put('/api/posts/'.$post->id, [
            'title' => 'Failed update',
            'content' => 'Post content',
            'thumbnail' => $this->thumbnail(),
        ], ['Accept' => 'application/json']);

        $response->assertInternalServerError();
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => $post->title, 'thumbnail' => $post->thumbnail]);
        $this->assertSame([$post->thumbnail], Storage::disk('public')->allFiles('posts'));
    }

    public function test_returns_500_without_creating_a_post_when_writing_the_image_fails(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::set('public', $disk);

        $response = $this->post('/api/posts', [
            'title' => 'Failed upload',
            'content' => 'Post content',
            'thumbnail' => $this->thumbnail(),
        ], ['Accept' => 'application/json']);

        $response->assertInternalServerError();
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_returns_500_and_preserves_the_post_when_writing_a_replacement_image_fails(): void
    {
        $originalDisk = Storage::fake('public');
        $user = User::factory()->create();
        $post = $this->postWithThumbnail($user);
        Sanctum::actingAs($user);
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::set('public', $disk);

        $response = $this->put('/api/posts/'.$post->id, [
            'title' => 'Failed replacement upload',
            'content' => 'Updated content',
            'thumbnail' => $this->thumbnail(),
        ], ['Accept' => 'application/json']);

        $response->assertInternalServerError();
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => $post->title, 'thumbnail' => $post->thumbnail]);
        $originalDisk->assertExists($post->thumbnail);
    }

    public function test_returns_thumbnail_paths_and_urls_in_the_list(): void
    {
        Storage::fake('public', ['url' => 'https://example.test/storage']);
        $post = $this->postWithThumbnail(User::factory()->create());

        $response = $this->getJson('/api/posts');

        $response->assertOk()
            ->assertJsonPath('data.0.thumbnail', $post->thumbnail)
            ->assertJsonPath('data.0.thumbnail_url', 'https://example.test/storage/'.$post->thumbnail);
    }

    public function test_returns_thumbnail_paths_and_urls_in_post_details(): void
    {
        Storage::fake('public', ['url' => 'https://example.test/storage']);
        $post = $this->postWithThumbnail(User::factory()->create());

        $response = $this->getJson('/api/posts/'.$post->id);

        $response->assertOk()
            ->assertJsonPath('data.thumbnail', $post->thumbnail)
            ->assertJsonPath('data.thumbnail_url', 'https://example.test/storage/'.$post->thumbnail);
    }

    public function test_keeps_the_image_when_the_post_is_soft_deleted(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $post = $this->postWithThumbnail($user);
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/posts/'.$post->id);

        $response->assertOk();
        $this->assertSoftDeleted($post);
        Storage::disk('public')->assertExists($post->thumbnail);
    }

    private function postWithThumbnail(User $user): Post
    {
        $path = $this->thumbnail()->store('posts', 'public');

        return Post::create([
            'title' => 'Original title',
            'content' => 'Original content',
            'user_id' => $user->id,
            'thumbnail' => $path,
        ]);
    }

    private function thumbnail(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('thumbnail.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='
        ));
    }
}
