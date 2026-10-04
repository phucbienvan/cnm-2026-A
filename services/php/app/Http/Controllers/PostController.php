<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\CreateRequest;
use App\Http\Requests\Post\UpdateRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PostController extends Controller
{
    /**
     * Khởi tạo controller với Dependency Injection cho Post model.
     */
    public function __construct(
        protected Post $postModel
    ) {}

    /**
     * Lấy danh sách toàn bộ bài viết (mới nhất trước).
     */
    public function index(): JsonResponse
    {
        $posts = $this->postModel->newQuery()
            ->latest('id')
            ->get();

        return response()->json([
            'data' => PostResource::collection($posts),
            'message' => 'Lấy bài viết thành công',
        ], Response::HTTP_OK);
    }

    /**
     * Tạo mới một bài viết từ dữ liệu đã xác thực.
     */
    public function store(CreateRequest $request): JsonResponse
    {
        $record = $this->postModel->create($request->validated());

        return response()->json([
            'message' => 'Thêm bài viết thành công',
            'data' => PostResource::make($record),
        ], Response::HTTP_OK);
    }

    /**
     * Chi tiết một bài viết cụ thể.
     */
    public function show(Post $post): JsonResponse
    {
        return response()->json([
            'message' => 'Lấy bài viết thành công',
            'data' => PostResource::make($post),
        ], Response::HTTP_OK);
    }

    /**
     * Cập nhật thông tin bài viết.
     */
    public function update(UpdateRequest $request, Post $post): JsonResponse
    {
        $post->fill($request->validated())->save();

        return response()->json([
            'message' => 'Cập nhật bài viết thành công',
            'data' => PostResource::make($post),
        ], Response::HTTP_OK);
    }

    /**
     * Xóa bài viết khỏi cơ sở dữ liệu.
     */
    public function destroy(Post $post): JsonResponse
    {
        $post->delete();

        return response()->json([
            'message' => 'Xóa bài viết thành công',
        ], Response::HTTP_OK);
    }
}


