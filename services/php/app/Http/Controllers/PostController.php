<?php

/**
 * @author Võ Đức Phú
 */

namespace App\Http\Controllers;

use App\Http\Requests\Post\CreateRequest;
use App\Http\Requests\Post\UpdateRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;

class PostController extends Controller
{
    public function index(): JsonResponse
    {
        $posts = Post::latest()->get();

        return response()->json([
            'data'    => PostResource::collection($posts),
            'message' => 'Lấy danh sách bài viết thành công',
        ]);
    }

    public function store(CreateRequest $request): JsonResponse
    {
        $post = Post::create($request->validated());

        return response()->json([
            'data'    => new PostResource($post),
            'message' => 'Thêm bài viết thành công',
        ], 201);
    }

    public function show(Post $post): JsonResponse
    {
        return response()->json([
            'data'    => new PostResource($post),
            'message' => 'Lấy bài viết thành công',
        ]);
    }

    public function update(UpdateRequest $request, Post $post): JsonResponse
    {
        $post->update($request->validated());

        return response()->json([
            'data'    => new PostResource($post->fresh()),
            'message' => 'Cập nhật bài viết thành công',
        ]);
    }

    public function destroy(Post $post): JsonResponse
    {
        $post->delete();

        return response()->json([
            'message' => 'Xóa bài viết thành công',
        ]);
    }

    public function update(CreateRequest $request, Post $post)
    {
        $input = $request->validated();

        $post->update([
            'title' => $input['title'],
            'content' => $input['content'],
        ]);

        return response()->json([
            'message' => 'Cập nhật bài viết thành công',
            'data' => new PostResource($post),
        ], 200);
    }
}
