<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\CreateRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PostController extends Controller
{
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
        $post = new Post;
        $post->user_id = $request->user()->id;
        $data = $this->savePost($request, $post);

        return response()->json([
            'message' => 'Thêm bài viết thành công',
            'data' => new PostResource($data),
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
        $user = auth()->user();

        if ($user->id != $post->user_id) {
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
        $user = $request->user();

        if ($user->id != $post->user_id) {
            return response()->json([
                'message' => 'Bạn không có quyền sửa bài viết này',
            ], 403);
        }

        $post = $this->savePost($request, $post);

        return response()->json([
            'message' => 'Cập nhật bài viết thành công',
            'data' => new PostResource($post),
        ], 200);
    }

    private function savePost(CreateRequest $request, Post $post): Post
    {
        $input = $request->validated();
        $file = $request->file('thumbnail');
        $thumbnail = $file ? 'thumbnails/'.$file->hashName() : null;
        $oldThumbnail = null;

        try {
            if ($file && ! $file->storeAs('thumbnails', $file->hashName(), 'public')) {
                throw new RuntimeException('Không thể lưu ảnh thumbnail.');
            }

            $post = DB::transaction(function () use ($post, $input, $thumbnail, &$oldThumbnail) {
                if ($post->exists) {
                    $post = Post::whereKey($post->id)->lockForUpdate()->firstOrFail();
                }

                $oldThumbnail = $post->thumbnail;
                $post->title = $input['title'];
                $post->content = $input['content'];

                if ($thumbnail !== null) {
                    $post->thumbnail = $thumbnail;
                }

                if (! $post->save()) {
                    throw new RuntimeException('Không thể lưu bài viết.');
                }

                return $post;
            });
        } catch (Throwable $exception) {
            if ($thumbnail !== null) {
                $this->deleteThumbnail($thumbnail);
            }

            throw $exception;
        }

        if ($thumbnail !== null && $oldThumbnail !== null) {
            $this->deleteThumbnail($oldThumbnail);
        }

        return $post;
    }

    private function deleteThumbnail(string $path): void
    {
        try {
            if (! Storage::disk('public')->delete($path)) {
                throw new RuntimeException("Không thể xóa thumbnail: {$path}");
            }
        } catch (Throwable $exception) {
            // Database đã lưu hoặc đang xử lý lỗi khác; ghi nhận lỗi dọn file để xử lý lại.
            report($exception);
        }
    }
}
