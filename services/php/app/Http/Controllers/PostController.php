<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\CreateRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;

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
        $data = Post::create([
            'title' => $input['title'],
            'content' => $input['content'],
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
        $post->delete();
        
        return response()->json([
            'message' => 'Xóa bài viết thành công'
        ], 200);
    }

    public function update(Request $request, Post $post)
    {
        $input = $request->validate([
            'title' => 'required',
            'content' => 'required',
        ]);

        $post->update([
            'title' => $input['title'],
            'content' => $input['content'],
        ]);

        return response()->json([
            'message' => 'Cập nhật bài viết thành công',
            'data' => new PostResource($post)
        ], 200);
    }
}
