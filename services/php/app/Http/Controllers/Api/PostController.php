<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Post\CreateRequest;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    // 1. GET ALL
    public function index()
    {
        $posts = Post::all();

        return response()->json([
            'status' => 'success',
            'data' => $posts,
        ], 200);
    }

    // 2. GET ONE (Chi tiết 1 bài viết)
    public function show($id)
    {
        $post = Post::find($id);
        if (! $post) {
            return response()->json([
                'message' => 'Không tìm thấy bài viết!',
            ], 404);
        }

        return response()->json([
            'data' => $post,
        ], 200);
    }

    // 3. CREATE (POST)
    public function store(CreateRequest $request)
    {
        $validatedData = $request->validated();

        $post = Post::create($validatedData);

        return response()->json([
            'message' => 'Tạo bài viết thành công!',
            'data' => $post,
        ], 201);
    }

    // 4. UPDATE (PUT)
    public function update(Request $request, $id)
    {
        $post = Post::find($id);

        if (! $post) {
            return response()->json([
                'message' => 'Không tìm thấy bài viết!',
            ], 404);
        }

        $validatedData = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
        ]);

        $post->update($validatedData);

        return response()->json([
            'message' => 'Cập nhật bài viết thành công!',
            'data' => $post,
        ], 200);
    }

    // 5. DELETE (Xóa mềm và cập nhật deleted_at)
    public function destroy($id)
    {
        $post = Post::find($id);

        if (! $post) {
            return response()->json([
                'message' => 'Không tìm thấy bài viết!',
            ], 404);
        }

        $post->delete();

        return response()->json([
            'message' => 'Đã xóa mềm bài viết thành công!',
            'data' => $post,
        ], 200);
    }
}
