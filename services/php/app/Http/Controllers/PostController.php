<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\CreateRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
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
        $thumbnailPath = null;

        try {
            if ($request->hasFile('thumbnail')) {
                $storedPath = $request->file('thumbnail')->store('posts/thumbnails', 'public');
                if (! is_string($storedPath)) {
                    throw new \RuntimeException('Unable to store post thumbnail.');
                }
                $thumbnailPath = $storedPath;
            }

            $data = DB::transaction(fn () => Post::create([
                'title' => $input['title'],
                'content' => $input['content'],
                'user_id' => $user->id,
                'thumbnail' => $thumbnailPath,
            ]));
        } catch (Throwable $exception) {
            if (is_string($thumbnailPath)) {
                Storage::disk('public')->delete($thumbnailPath);
            }

            throw $exception;
        }
        
        return response()->json([
            'message' => 'Thêm bài viết thành công',
            'data' => new PostResource($data)
        ], 200);
    }

    public function show(Post $post)
    {        
        return response()->json([
            'message' => 'lấy bài viết thành công',
            'data' => new PostResource($post)
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
            'message' => 'Xóa bài viết thành công'
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

        $oldThumbnailPath = $post->thumbnail;
        $newThumbnailPath = null;

        try {
            if ($request->hasFile('thumbnail')) {
                $storedPath = $request->file('thumbnail')->store('posts/thumbnails', 'public');
                if (! is_string($storedPath)) {
                    throw new \RuntimeException('Unable to store post thumbnail.');
                }
                $newThumbnailPath = $storedPath;
            }

            DB::transaction(function () use ($input, $post, $newThumbnailPath): void {
                $post->update([
                    'title' => $input['title'],
                    'content' => $input['content'],
                    'thumbnail' => $newThumbnailPath ?? $post->thumbnail,
                ]);
            });
        } catch (Throwable $exception) {
            if (is_string($newThumbnailPath)) {
                Storage::disk('public')->delete($newThumbnailPath);
            }

            throw $exception;
        }

        if ($newThumbnailPath !== null && $oldThumbnailPath !== null) {
            Storage::disk('public')->delete($oldThumbnailPath);
        }

        return response()->json([
            'message' => 'Cập nhật bài viết thành công',
            'data' => new PostResource($post),
        ], 200);
    }
}
