<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PostThumbnailTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        // Neutralize DB_URL and cached database config before test migrations.
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.url', null);
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

        return $app;
    }

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

    private function image(string $name): UploadedFile
    {
        $contents = match (pathinfo($name, PATHINFO_EXTENSION)) {
            'jpg' => '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAMCAgMCAgMDAwMEAwMEBQgFBQQEBQoHBwYIDAoMDAsKCwsNDhIQDQ4RDgsLEBYQERMUFRUVDA8XGBYUGBIUFRT/2wBDAQMEBAUEBQkFBQkUDQsNFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBT/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD8qqKKKAP/2Q==',
            'webp' => 'UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEADsD+JaQAA3AAAAAA',
            'gif' => 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7',
            default => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aF9sAAAAASUVORK5CYII=',
        };

        return UploadedFile::fake()->createWithContent($name, base64_decode($contents));
    }

    private function postWithThumbnail(User $user): Post
    {
        $path = $this->image('old.jpg')->store('posts/thumbnails', 'public');

        return Post::create(['title' => 'Old title', 'content' => 'Old content', 'user_id' => $user->id, 'thumbnail' => $path]);
    }

    public function test_create_without_image_returns_null_and_preserves_owner(): void
    {
        $user = $this->signIn();

        $response = $this->postJson('/api/posts', ['title' => 'Title', 'content' => 'Content', 'user_id' => 999]);

        $response->assertOk()->assertJsonPath('data.thumbnail', null)->assertJsonPath('data.thumbnail_url', null)
            ->assertJsonPath('data.user_id', $user->id)->assertJsonPath('data.title', 'Title');
        $this->assertDatabaseHas('posts', ['id' => $response->json('data.id'), 'thumbnail' => null]);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public static function formats(): array
    {
        return ['JPEG' => ['jpg'], 'PNG' => ['png'], 'WebP' => ['webp']];
    }

    #[DataProvider('formats')]
    public function test_create_stores_image_and_returns_path_and_url(string $extension): void
    {
        $this->signIn();

        $response = $this->post('/api/posts', ['title' => 'Title', 'content' => 'Content',
            'thumbnail' => $this->image('client-name.'.$extension),
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $path = $response->json('data.thumbnail');
        $this->assertStringStartsWith('posts/thumbnails/', $path);
        $this->assertStringNotContainsString('client-name', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('posts', ['id' => $response->json('data.id'), 'thumbnail' => $path]);
        $url = Storage::disk('public')->url($path);
        $response->assertJsonPath('data.thumbnail_url', $url);
        $this->getJson('/api/posts/'.$response->json('data.id'))->assertOk()->assertJsonPath('data.thumbnail_url', $url);
        $this->getJson('/api/posts')->assertOk()->assertJsonPath('data.0.thumbnail', $path);
    }

    public static function invalidFiles(): array
    {
        return ['text' => ['text'], 'spoofed JPEG' => ['spoofed'], 'SVG' => ['svg'], 'GIF' => ['gif'],
            'over 2 MB' => ['oversized'], 'client path' => ['path']];
    }

    #[DataProvider('invalidFiles')]
    public function test_invalid_upload_returns_422_without_changing_files_or_database(string $kind): void
    {
        $post = $this->postWithThumbnail($this->signIn());
        $file = match ($kind) {
            'text' => UploadedFile::fake()->createWithContent('notes.txt', 'plain text'),
            'spoofed' => UploadedFile::fake()->createWithContent('photo.jpg', 'not an image'),
            'svg' => UploadedFile::fake()->createWithContent('photo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            'gif' => $this->image('photo.gif'),
            'oversized' => $this->image('large.jpg')->size(2049),
            'path' => 'posts/thumbnails/arbitrary.jpg',
        };

        if ($kind === 'spoofed') {
            $source = $file;
            $file = new UploadedFile($source->getPathname(), 'photo.jpg', 'image/jpeg', null, true);
        }

        foreach (['/api/posts', '/api/posts/'.$post->id] as $index => $url) {
            $this->post($url, ['_method' => $index === 0 ? 'POST' : 'PUT', 'title' => 'New', 'content' => 'New', 'thumbnail' => $file],
                ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('thumbnail');
        }

        $this->assertDatabaseCount('posts', 1);
        $this->assertSame('Old title', $post->fresh()->title);
        $this->assertSame([$post->thumbnail], Storage::disk('public')->allFiles());
    }

    public function test_update_without_file_keeps_old_image(): void
    {
        $post = $this->postWithThumbnail($this->signIn());

        foreach ([[], ['thumbnail' => null]] as $input) {
            $this->putJson('/api/posts/'.$post->id, $input + ['title' => 'New', 'content' => 'New'])
                ->assertOk()->assertJsonPath('data.thumbnail', $post->thumbnail);
        }

        Storage::disk('public')->assertExists($post->thumbnail);
        $this->assertSame($post->thumbnail, $post->fresh()->thumbnail);
    }

    public function test_existing_post_without_image_accepts_image_at_2_mb_limit(): void
    {
        $user = $this->signIn();
        $post = Post::create(['title' => 'Old', 'content' => 'Old', 'user_id' => $user->id]);

        $response = $this->post('/api/posts/'.$post->id, ['_method' => 'PUT', 'title' => 'New', 'content' => 'New',
            'thumbnail' => $this->image('photo.jpg')->size(2048),
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $path = $response->json('data.thumbnail');
        Storage::disk('public')->assertExists($path);
        $this->assertSame($path, $post->fresh()->thumbnail);
    }

    public function test_replacement_deletes_only_old_image_after_database_save(): void
    {
        $post = $this->postWithThumbnail($this->signIn());
        $other = $this->postWithThumbnail($post->user);
        Post::saved(function () use ($post): void {
            Storage::disk('public')->assertExists($post->thumbnail);
        });

        $response = $this->post('/api/posts/'.$post->id, ['_method' => 'PUT', 'title' => 'New', 'content' => 'New',
            'user_id' => 999, 'thumbnail' => $this->image('replacement.png'),
        ], ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonPath('data.user_id', $post->user_id);
        $newPath = $response->json('data.thumbnail');
        $this->assertNotSame($post->thumbnail, $newPath);
        $this->assertSame($newPath, $post->fresh()->thumbnail);
        Storage::disk('public')->assertMissing($post->thumbnail);
        Storage::disk('public')->assertExists([$newPath, $other->thumbnail]);
    }

    public static function failingSaves(): array
    {
        return ['create' => ['create', 'saving'], 'update' => ['update', 'saving'],
            'after write' => ['update', 'saved'], 'cancelled save' => ['update', 'cancel']];
    }

    #[DataProvider('failingSaves')]
    public function test_database_failure_cleans_new_image_and_preserves_old_image(string $operation, string $event): void
    {
        $post = $this->postWithThumbnail($this->signIn());
        if ($event === 'cancel') {
            Post::saving(fn () => false);
        } else {
            Post::{$event}(function (): void {
                throw new RuntimeException('Simulated database failure');
            });
        }
        Exceptions::fake();

        $this->post($operation === 'create' ? '/api/posts' : '/api/posts/'.$post->id, [
            '_method' => $operation === 'create' ? 'POST' : 'PUT', 'title' => 'New', 'content' => 'New',
            'thumbnail' => $this->image('new.jpg'),
        ], ['Accept' => 'application/json'])->assertInternalServerError();

        Exceptions::assertReported(fn (RuntimeException $exception) => in_array($exception->getMessage(), ['Simulated database failure', 'Unable to save post.'], true));
        $this->assertSame([$post->thumbnail], Storage::disk('public')->allFiles());
        $this->assertSame($post->thumbnail, $post->fresh()->thumbnail);
        $this->assertSame('Old title', $post->fresh()->title);
        $this->assertDatabaseCount('posts', 1);
    }

    public function test_storage_failure_preserves_database_and_old_image(): void
    {
        $post = $this->postWithThumbnail($this->signIn());
        $fake = Storage::disk('public');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        Exceptions::fake();

        $this->post('/api/posts/'.$post->id, ['_method' => 'PUT', 'title' => 'New', 'content' => 'New',
            'thumbnail' => $this->image('new.jpg'),
        ], ['Accept' => 'application/json'])->assertInternalServerError();

        Exceptions::assertReported(fn (RuntimeException $exception) => $exception->getMessage() === 'Unable to store post thumbnail.');
        $this->assertSame($post->thumbnail, $post->fresh()->thumbnail);
        $this->assertSame('Old title', $post->fresh()->title);
        $fake->assertExists($post->thumbnail);
    }

    public function test_guests_cannot_upload_or_replace_images_401(): void
    {
        $post = $this->postWithThumbnail(User::factory()->create());

        foreach (['/api/posts', '/api/posts/'.$post->id] as $index => $url) {
            $this->post($url, ['_method' => $index === 0 ? 'POST' : 'PUT', 'title' => 'New', 'content' => 'New',
                'thumbnail' => $this->image('new.jpg'),
            ], ['Accept' => 'application/json'])->assertUnauthorized();
        }

        $this->assertSame([$post->thumbnail], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('posts', 1);
    }

    public function test_other_users_cannot_replace_image_403(): void
    {
        $post = $this->postWithThumbnail(User::factory()->create());
        $this->signIn();

        $this->post('/api/posts/'.$post->id, ['_method' => 'PUT', 'title' => 'New', 'content' => 'New',
            'thumbnail' => $this->image('new.jpg'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->assertSame([$post->thumbnail], Storage::disk('public')->allFiles());
        $this->assertSame($post->thumbnail, $post->fresh()->thumbnail);
    }

    public function test_soft_delete_preserves_image_for_restore(): void
    {
        $post = $this->postWithThumbnail($this->signIn());

        $this->deleteJson('/api/posts/'.$post->id)->assertOk();

        $this->assertSoftDeleted($post);
        Storage::disk('public')->assertExists($post->thumbnail);
        $post->refresh()->restore();
        $this->getJson('/api/posts/'.$post->id)->assertOk()->assertJsonPath('data.thumbnail', $post->thumbnail);
    }
}
