<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\CreateRequest;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index()
    {        
        $posts = Post::orderByDesc('id')->get();

        return response()->json([
            'data' => $posts
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
            'data' => $data
        ], 200);
    }

    public function show(Post $post)
    {        
        return response()->json([
            'message' => 'lấy bài viết thành công',
            'data' => $post
        ], 200);
    }

    public function destroy(Post $post)
    {
        $post->delete();
        
        return response()->json([
            'message' => 'Xóa bài viết thành công'
        ], 200);
    }
}
