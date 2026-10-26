<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\CreateRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('thumbnails', 'public');
        }

        $data = Post::create([
            'title' => $input['title'],
            'content' => $input['content'],
            'user_id' => $user->id,
            'thumbnail' => $thumbnailPath,
        ]);
        
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

        if ($post->thumbnail) {
            Storage::disk('public')->delete($post->thumbnail);
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

        $thumbnailPath = $post->thumbnail;
        if ($request->hasFile('thumbnail')) {
            // Xóa ảnh cũ nếu có
            if ($post->thumbnail) {
                Storage::disk('public')->delete($post->thumbnail);
            }
            $thumbnailPath = $request->file('thumbnail')->store('thumbnails', 'public');
        }

        $post->update([
            'title' => $input['title'],
            'content' => $input['content'],
            'thumbnail' => $thumbnailPath,
        ]);

        return response()->json([
            'message' => 'Cập nhật bài viết thành công',
            'data' => new PostResource($post),
        ], 200);
    }
}
