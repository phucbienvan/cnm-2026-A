<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PostThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private ?UploadedFile $invalidImage = null;

    protected function beforeRefreshingDatabase(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
        Storage::fake('public');
    }

    private function signIn(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        return $user;
    }

    private function createPost(User $user): Post
    {
        Storage::disk('public')->put('thumbnails/old.jpg', 'old image');
        return Post::create([
            'title' => 'Bài viết cũ',
            'content' => 'Nội dung cũ',
            'user_id' => $user->id,
            'thumbnail' => 'thumbnails/old.jpg',
        ]);
    }

    public function test_create_without_image_uses_authenticated_owner(): void
    {
        $user = $this->signIn();
        $other = User::factory()->create();
        $response = $this->postJson('/api/posts', [
            'title' => 'Bài mới', 'content' => 'Nội dung', 'user_id' => $other->id,
        ])->assertOk()->assertJsonPath('data.thumbnail', null)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonStructure(['message', 'data' => ['id', 'title', 'content', 'user_id', 'user']]);
        $this->assertDatabaseHas('posts', ['id' => $response->json('data.id'), 'user_id' => $user->id, 'thumbnail' => null]);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    #[DataProvider('imageExtensions')]
    public function test_create_accepts_supported_images(string $extension): void
    {
        $this->signIn();
        $response = $this->postJson('/api/posts', [
            'title' => 'Ảnh mới', 'content' => 'Nội dung',
            'thumbnail' => UploadedFile::fake()->image('client-name.'.$extension)->size(2048),
        ])->assertOk();
        $post = Post::findOrFail($response->json('data.id'));
        $this->assertStringStartsWith('thumbnails/', $post->thumbnail);
        $this->assertStringNotContainsString('client-name', $post->thumbnail);
        Storage::disk('public')->assertExists($post->thumbnail);
        $response->assertJsonPath('data.thumbnail', Storage::disk('public')->url($post->thumbnail));
        $this->getJson('/api/posts/'.$post->id)->assertOk()->assertJsonPath('data.thumbnail', Storage::disk('public')->url($post->thumbnail));
        $this->getJson('/api/posts')->assertOk()->assertJsonPath('data.0.thumbnail', Storage::disk('public')->url($post->thumbnail));
    }

    public static function imageExtensions(): array
    {
        return [['jpg'], ['jpeg'], ['png'], ['webp']];
    }

    #[DataProvider('invalidImages')]
    public function test_invalid_upload_does_not_change_database_or_files(string $kind): void
    {
        $user = $this->signIn();
        $post = $this->createPost($user);
        $file = match ($kind) {
            'gif' => UploadedFile::fake()->image('image.gif'),
            'oversized' => UploadedFile::fake()->image('image.png')->size(2049),
            'fake-jpg' => $this->fakeJpeg(),
            'svg' => UploadedFile::fake()->createWithContent('image.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        };
        $data = ['title' => 'Tên mới', 'content' => 'Nội dung mới', 'thumbnail' => $file];
        $this->postJson('/api/posts', $data)->assertUnprocessable()->assertJsonValidationErrors('thumbnail');
        $this->putJson('/api/posts/'.$post->id, $data)->assertUnprocessable()->assertJsonValidationErrors('thumbnail');
        $this->assertDatabaseCount('posts', 1);
        $this->assertSame('Bài viết cũ', $post->fresh()->title);
        $this->assertSame(['thumbnails/old.jpg'], Storage::disk('public')->allFiles());
    }

    private function fakeJpeg(): UploadedFile
    {
        $this->invalidImage = UploadedFile::fake()->createWithContent('image.jpg', 'not an image');
        return new UploadedFile($this->invalidImage->getPathname(), 'image.jpg', null, null, true);
    }

    public static function invalidImages(): array
    {
        return [['gif'], ['oversized'], ['fake-jpg'], ['svg']];
    }

    public function test_update_without_image_or_with_null_preserves_thumbnail(): void
    {
        $user = $this->signIn();
        $post = $this->createPost($user);
        foreach ([[], ['thumbnail' => null]] as $extra) {
            $this->putJson('/api/posts/'.$post->id, array_merge([
                'title' => 'Đã sửa', 'content' => 'Nội dung mới', 'user_id' => 999,
            ], $extra))->assertOk();
            $this->assertSame('thumbnails/old.jpg', $post->fresh()->thumbnail);
            $this->assertEquals($user->id, $post->fresh()->user_id);
            Storage::disk('public')->assertExists('thumbnails/old.jpg');
        }
    }

    public function test_multipart_update_replaces_old_image_after_saving(): void
    {
        $post = $this->createPost($this->signIn());
        $this->post('/api/posts/'.$post->id, [
            '_method' => 'PUT', 'title' => 'Ảnh thay thế', 'content' => 'Nội dung',
            'thumbnail' => UploadedFile::fake()->image('new.png'),
        ], ['Accept' => 'application/json'])->assertOk();
        $newPath = $post->fresh()->thumbnail;
        $this->assertNotSame('thumbnails/old.jpg', $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing('thumbnails/old.jpg');
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_non_owner_cannot_update_or_delete(): void
    {
        $post = $this->createPost(User::factory()->create());
        $this->signIn();
        $this->putJson('/api/posts/'.$post->id, [
            'title' => 'Sửa trái phép', 'content' => 'Nội dung',
            'thumbnail' => UploadedFile::fake()->image('new.png'),
        ])->assertForbidden();
        $this->deleteJson('/api/posts/'.$post->id)->assertForbidden();
        $this->assertNull($post->fresh()->deleted_at);
        $this->assertSame('Bài viết cũ', $post->fresh()->title);
        $this->assertSame(['thumbnails/old.jpg'], Storage::disk('public')->allFiles());
    }

    public function test_guests_cannot_write_but_can_read_posts(): void
    {
        $post = $this->createPost(User::factory()->create());
        $data = ['title' => 'Tên mới', 'content' => 'Nội dung', 'thumbnail' => UploadedFile::fake()->image('new.png')];
        $this->postJson('/api/posts', $data)->assertUnauthorized();
        $this->putJson('/api/posts/'.$post->id, $data)->assertUnauthorized();
        $this->deleteJson('/api/posts/'.$post->id)->assertUnauthorized();
        $this->getJson('/api/posts/'.$post->id)->assertOk();
        $this->getJson('/api/posts')->assertOk();
        $this->assertSame(['thumbnails/old.jpg'], Storage::disk('public')->allFiles());
    }

    public function test_missing_post_returns_404_without_storing_image(): void
    {
        $this->signIn();
        $this->putJson('/api/posts/999', [
            'title' => 'Tên mới', 'content' => 'Nội dung', 'thumbnail' => UploadedFile::fake()->image('new.png'),
        ])->assertNotFound();
        $this->deleteJson('/api/posts/999')->assertNotFound();
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_soft_delete_keeps_image_for_restore(): void
    {
        $post = $this->createPost($this->signIn());
        $this->deleteJson('/api/posts/'.$post->id)->assertOk();
        $this->assertSoftDeleted($post);
        Storage::disk('public')->assertExists('thumbnails/old.jpg');
        $this->getJson('/api/posts/'.$post->id)->assertNotFound();
        $post->restore();
        $this->getJson('/api/posts/'.$post->id)->assertOk()
            ->assertJsonPath('data.thumbnail', Storage::disk('public')->url('thumbnails/old.jpg'));
    }

    #[DataProvider('writeOperations')]
    public function test_database_failure_removes_new_image_and_preserves_old_data(string $operation): void
    {
        $post = $this->createPost($this->signIn());
        DB::unprepared("CREATE TRIGGER fail_post_write BEFORE {$operation} ON posts BEGIN SELECT RAISE(ABORT, 'test failure'); END");
        $data = ['title' => 'Tên mới', 'content' => 'Nội dung mới', 'thumbnail' => UploadedFile::fake()->image('new.png')];
        $response = $operation === 'INSERT'
            ? $this->postJson('/api/posts', $data)
            : $this->putJson('/api/posts/'.$post->id, $data);
        $response->assertStatus(500)->assertJsonMissingPath('exception');
        $this->assertDatabaseCount('posts', 1);
        $this->assertSame('Bài viết cũ', $post->fresh()->title);
        $this->assertSame('thumbnails/old.jpg', $post->fresh()->thumbnail);
        $this->assertSame(['thumbnails/old.jpg'], Storage::disk('public')->allFiles());
    }

    public static function writeOperations(): array
    {
        return [['INSERT'], ['UPDATE']];
    }

    #[DataProvider('uploadFailures')]
    public function test_failed_upload_cleans_partial_file_and_keeps_old_image(bool $throws): void
    {
        $post = $this->createPost($this->signIn());
        $disk = Storage::disk('public');
        $failingDisk = Mockery::mock(FilesystemAdapter::class);
        $failingDisk->shouldReceive('putFileAs')->once()->andReturnUsing(function ($directory, $file, $name) use ($disk, $throws) {
            $disk->put($directory.'/'.$name, 'partial');
            if ($throws) {
                throw new RuntimeException('Simulated upload failure');
            }
            return false;
        });
        $failingDisk->shouldReceive('delete')->once()->andReturnUsing(fn ($path) => $disk->delete($path));
        Storage::shouldReceive('disk')->with('public')->andReturn($failingDisk);
        $this->putJson('/api/posts/'.$post->id, [
            'title' => 'Tên mới', 'content' => 'Nội dung', 'thumbnail' => UploadedFile::fake()->image('new.png'),
        ])->assertStatus(500);
        $this->assertSame('thumbnails/old.jpg', $post->fresh()->thumbnail);
        $this->assertSame('Bài viết cũ', $post->fresh()->title);
        $this->assertSame(['thumbnails/old.jpg'], $disk->allFiles());
    }

    public static function uploadFailures(): array
    {
        return [[false], [true]];
    }

    public function test_migration_can_rollback_without_losing_posts(): void
    {
        $post = $this->createPost($this->signIn());
        $migration = require database_path('migrations/2026_10_26_133704_add_thumbnail_to_posts_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('posts', 'thumbnail'));
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => 'Bài viết cũ']);
        $migration->up();
        $this->assertTrue(Schema::hasColumn('posts', 'thumbnail'));
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'thumbnail' => null]);
    }

    public function test_cleanup_failure_is_reported_without_removing_new_thumbnail(): void
    {
        Exceptions::fake();
        $post = $this->createPost($this->signIn());
        $disk = Storage::disk('public');
        $failingDisk = Mockery::mock(FilesystemAdapter::class);
        $failingDisk->shouldReceive('putFileAs')->once()->andReturnUsing(fn (...$arguments) => $disk->putFileAs(...$arguments));
        $failingDisk->shouldReceive('delete')->with('thumbnails/old.jpg')->once()->andReturn(false);
        $failingDisk->shouldReceive('url')->andReturnUsing(fn ($path) => $disk->url($path));
        Storage::shouldReceive('disk')->with('public')->andReturn($failingDisk);
        $this->putJson('/api/posts/'.$post->id, [
            'title' => 'Ảnh mới', 'content' => 'Nội dung', 'thumbnail' => UploadedFile::fake()->image('new.png'),
        ])->assertOk();
        $newPath = $post->fresh()->thumbnail;
        $this->assertNotSame('thumbnails/old.jpg', $newPath);
        $disk->assertExists($newPath);
        $disk->assertExists('thumbnails/old.jpg');
        Exceptions::assertReported(fn (RuntimeException $exception) => str_contains($exception->getMessage(), 'Không thể xóa thumbnail'));
    }

    public function test_validation_preserves_required_text_fields_and_rejects_file_paths(): void
    {
        $post = $this->createPost($this->signIn());
        $this->putJson('/api/posts/'.$post->id, [
            'thumbnail' => UploadedFile::fake()->image('new.png'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['title', 'content']);
        $this->putJson('/api/posts/'.$post->id, [
            'title' => 'Tên mới', 'content' => 'Nội dung', 'thumbnail' => 'thumbnails/another-user.jpg',
        ])->assertUnprocessable()->assertJsonValidationErrors('thumbnail');
        $this->assertSame(['thumbnails/old.jpg'], Storage::disk('public')->allFiles());
    }
}
