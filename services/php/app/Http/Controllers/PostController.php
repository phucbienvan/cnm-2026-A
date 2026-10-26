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
        $user = auth()->user();

        $thumbnail = $request->hasFile('thumbnail')
            ? $this->storeThumbnail($request->file('thumbnail'))
            : null;

        try {
            $data = Post::create([
                'title' => $input['title'],
                'content' => $input['content'],
                'user_id' => $user->id,
                'thumbnail' => $thumbnail,
            ]);
        } catch (Throwable $exception) {
            if ($thumbnail !== null) {
                Storage::disk('public')->delete($thumbnail);
            }

            throw $exception;
        }

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
        $input = $request->validated();
        $user = auth()->user();

        if ($user->id != $post->user_id) {
            return response()->json([
                'message' => 'Bạn không có quyền sửa bài viết này',
            ], 403);
        }

        $data = [
            'title' => $input['title'],
            'content' => $input['content'],
        ];
        $oldThumbnail = $post->thumbnail;

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $this->storeThumbnail($request->file('thumbnail'));
        }

        try {
            $post->update($data);
        } catch (Throwable $exception) {
            if (isset($data['thumbnail'])) {
                Storage::disk('public')->delete($data['thumbnail']);
            }

            throw $exception;
        }

        if (isset($data['thumbnail']) && $oldThumbnail !== null) {
            Storage::disk('public')->delete($oldThumbnail);
        }

        return response()->json([
            'message' => 'Cập nhật bài viết thành công',
            'data' => new PostResource($post),
        ], 200);
    }

    private function storeThumbnail(UploadedFile $file): string
    {
        $path = $file->store('posts/thumbnails', 'public');

        if ($path === false) {
            throw new RuntimeException('Unable to store the post thumbnail.');
        }

        return $path;
    }
}
