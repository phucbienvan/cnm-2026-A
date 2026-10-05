<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostController extends Controller
{
    public function index()
    {        
        $posts = Post::orderByDesc('id')->get();

        return response()->json([
            'data' => PostResource::collection($posts),
            'message' => 'Lấy bài viết thành công',
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
            'message' => 'Lấy bài viết thành công',
            'data' => new PostResource($post)
        ], 200);
    }

    public function update(UpdateRequest $request, Post $post)
    {
        $input = $request->validated();

        $post->update($input);

        return response()->json([
            'message' => 'Cập nhật bài viết thành công',
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
}
