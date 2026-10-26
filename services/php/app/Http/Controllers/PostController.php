<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\CreateRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PostController extends Controller
{
    private const THUMBNAIL_DISK = 'public';
    private const THUMBNAIL_DIR = 'posts/thumbnails';

    public function index()
    {
        $posts = Post::orderByDesc('id')->get();

        return response()->json([
            'data' => PostResource::collection($posts),
            'message' => 'lấy bài viết thành công',
        ], 200);
    }

    public function store(CreateRequest $request)
    {
        $input = $request->validated();
        $thumbnailPath = $this->uploadThumbnail($request->file('thumbnail'));

        try {
            $post = Post::create([
                'title' => $input['title'],
                'content' => $input['content'],
                'user_id' => auth()->id(),
                'thumbnail' => $thumbnailPath,
            ]);
        } catch (Throwable $e) {
            // Tạo bài viết lỗi thì dọn file vừa upload
            $this->removeThumbnail($thumbnailPath);

            throw $e;
        }

        return response()->json([
            'message' => 'Thêm bài viết thành công',
            'data' => new PostResource($post),
        ], 200);
    }

    public function show(Post $post)
    {
        return response()->json([
            'message' => 'lấy bài viết thành công',
            'data' => new PostResource($post),
        ], 200);
    }

    public function destroy(Post $post)
    {
        if (auth()->id() != $post->user_id) {
            return response()->json([
                'message' => 'Bạn không có quyền xóa bài viết này',
            ], 403);
        }

        $post->delete();

        return response()->json([
            'message' => 'Xóa bài viết thành công',
        ], 200);
    }

    public function update(CreateRequest $request, Post $post)
    {
        if (auth()->id() != $post->user_id) {
            return response()->json([
                'message' => 'Bạn không có quyền sửa bài viết này',
            ], 403);
        }

        $input = $request->validated();
        $oldThumbnail = $post->thumbnail;
        $newThumbnail = $this->uploadThumbnail($request->file('thumbnail'));

        $payload = [
            'title' => $input['title'],
            'content' => $input['content'],
        ];

        if ($newThumbnail !== null) {
            $payload['thumbnail'] = $newThumbnail;
        }

        try {
            $post->update($payload);
        } catch (Throwable $e) {
            // Cập nhật lỗi thì bỏ file mới, giữ nguyên file cũ
            $this->removeThumbnail($newThumbnail);

            throw $e;
        }

        // Cập nhật thành công và có ảnh mới thì mới xóa ảnh cũ
        if ($newThumbnail !== null) {
            $this->removeThumbnail($oldThumbnail);
        }

        return response()->json([
            'message' => 'Cập nhật bài viết thành công',
            'data' => new PostResource($post),
        ], 200);
    }

    /**
     * Lưu file thumbnail, trả về đường dẫn. Không có file thì trả về null.
     */
    private function uploadThumbnail(?UploadedFile $file): ?string
    {
        if ($file === null) {
            return null;
        }

        $path = $file->store(self::THUMBNAIL_DIR, self::THUMBNAIL_DISK);

        if ($path === false) {
            throw new RuntimeException('Unable to store the post thumbnail.');
        }

        return $path;
    }

    /**
     * Xóa file thumbnail khỏi disk, bỏ qua nếu đường dẫn rỗng.
     */
    private function removeThumbnail(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(self::THUMBNAIL_DISK)->delete($path);
        }
    }
}