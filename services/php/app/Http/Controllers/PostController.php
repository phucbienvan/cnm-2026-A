<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\CreateRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
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

    public function store(CreateRequest $request): JsonResponse
    {
        $input = $request->validated();
        $thumbnailPath = $this->storeThumbnail($request->file('thumbnail'));
        $data = Post::create([
            'title' => $input['title'],
            'content' => $input['content'],
            'thumbnail' => $thumbnailPath,
        ]);

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
        $post->delete();

        return response()->json([
            'message' => 'Xóa bài viết thành công',
        ], 200);
    }

    public function update(CreateRequest $request, Post $post): JsonResponse
    {
        $input = $request->validated();
        $oldThumbnailPath = $post->thumbnail;
        $thumbnailPath = $this->storeThumbnail($request->file('thumbnail'));

        $attributes = [
            'title' => $input['title'],
            'content' => $input['content'],
        ];

        if ($thumbnailPath !== null) {
            $attributes['thumbnail'] = $thumbnailPath;
        }

        $post->update($attributes);

        if ($thumbnailPath !== null && $oldThumbnailPath !== null) {
            $deleted = Storage::disk('public')->delete($oldThumbnailPath);

            if (! $deleted) {
                throw new \RuntimeException('Unable to delete previous post thumbnail.');
            }
        }

        return response()->json([
            'message' => 'Cập nhật bài viết thành công',
            'data' => new PostResource($post),
        ], 200);
    }

    private function storeThumbnail(?UploadedFile $thumbnail): ?string
    {
        if ($thumbnail === null) {
            return null;
        }

        $path = $thumbnail->store('thumbnails/posts', 'public');

        if ($path === false) {
            throw new \RuntimeException('Unable to store post thumbnail.');
        }

        return $path;
    }
}
