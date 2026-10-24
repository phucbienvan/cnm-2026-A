<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UpdatePostTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        // Dùng SQLite trong bộ nhớ để không ảnh hưởng dữ liệu đang có.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
    }

    public function test_update_saves_valid_fields_and_ignores_extra_fields(): void
    {
        $post = Post::create(['title' => 'Tiêu đề cũ', 'content' => 'Nội dung cũ']);

        $this->putJson('/api/posts/'.$post->id, [
            'title' => 'Tiêu đề mới',
            'content' => 'Nội dung mới',
            'id' => 999,
            'deleted_at' => '2026-01-01 00:00:00',
        ])->assertOk()->assertExactJson([
            'message' => 'Đã lưu thay đổi bài viết',
            'data' => [
                'id' => $post->id,
                'title' => 'Tiêu đề mới',
                'content' => 'Nội dung mới',
            ],
        ]);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Tiêu đề mới',
            'content' => 'Nội dung mới',
            'deleted_at' => null,
        ]);
        $this->assertDatabaseCount('posts', 1);
    }

    #[DataProvider('invalidPostData')]
    public function test_invalid_data_does_not_change_the_post(array $data, string $field, string $message): void
    {
        $post = Post::create(['title' => 'Tiêu đề cũ', 'content' => 'Nội dung cũ']);

        $this->putJson('/api/posts/'.$post->id, $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field])
            ->assertJsonPath('errors.'.$field.'.0', $message);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Tiêu đề cũ',
            'content' => 'Nội dung cũ',
        ]);
    }

    public static function invalidPostData(): array
    {
        return [
            'missing title' => [['content' => 'Nội dung mới'], 'title', 'Vui lòng nhập tiêu đề bài viết.'],
            'blank title' => [['title' => '   ', 'content' => 'Nội dung mới'], 'title', 'Vui lòng nhập tiêu đề bài viết.'],
            'title too long' => [['title' => str_repeat('a', 256), 'content' => 'Nội dung mới'], 'title', 'Tiêu đề không được vượt quá 255 ký tự.'],
            'invalid title type' => [['title' => ['abc'], 'content' => 'Nội dung mới'], 'title', 'Tiêu đề phải là chuỗi ký tự.'],
            'missing content' => [['title' => 'Tiêu đề mới'], 'content', 'Vui lòng nhập nội dung bài viết.'],
            'blank content' => [['title' => 'Tiêu đề mới', 'content' => '   '], 'content', 'Vui lòng nhập nội dung bài viết.'],
            'invalid content type' => [['title' => 'Tiêu đề mới', 'content' => 123], 'content', 'Nội dung phải là chuỗi ký tự.'],
        ];
    }

    public function test_missing_post_returns_not_found(): void
    {
        $this->putJson('/api/posts/999', ['title' => 'Tiêu đề', 'content' => 'Nội dung'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Không tìm thấy bài viết');
    }

    public function test_deleted_post_cannot_be_updated(): void
    {
        $post = Post::create(['title' => 'Tiêu đề cũ', 'content' => 'Nội dung cũ']);
        $post->delete();

        $this->putJson('/api/posts/'.$post->id, ['title' => 'Tiêu đề mới', 'content' => 'Nội dung mới'])
            ->assertNotFound();

        $this->assertSoftDeleted($post);
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Tiêu đề cũ',
            'content' => 'Nội dung cũ',
        ]);
    }
}
