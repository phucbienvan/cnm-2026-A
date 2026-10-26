<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\CreateRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;
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
            ? $request->file('thumbnail')->store('posts', 'public')
            : null;

        try {
            $data = Post::create([
                'title' => $input['title'],
                'content' => $input['content'],
                'user_id' => $user->id,
                'thumbnail' => $thumbnail,
            ]);
        } catch (Throwable $e) {
            if ($thumbnail) {
                Storage::disk('public')->delete($thumbnail);
            }

            throw $e;
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

        $oldThumbnail = $post->thumbnail;
        $newThumbnail = $request->hasFile('thumbnail')
            ? $request->file('thumbnail')->store('posts', 'public')
            : null;

        $updateData = [
            'title' => $input['title'],
            'content' => $input['content'],
        ];

        if ($newThumbnail) {
            $updateData['thumbnail'] = $newThumbnail;
        }

        try {
            $post->update($updateData);
        } catch (Throwable $e) {
            if ($newThumbnail) {
                Storage::disk('public')->delete($newThumbnail);
            }

            throw $e;
        }

        if ($newThumbnail && $oldThumbnail) {
            Storage::disk('public')->delete($oldThumbnail);
        }

        return response()->json([
            'message' => 'Cập nhật bài viết thành công',
            'data' => new PostResource($post),
        ], 200);
    }
}
