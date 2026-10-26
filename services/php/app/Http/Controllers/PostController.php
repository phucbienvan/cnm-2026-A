<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\CreateRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
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

        $data = $this->savePost(new Post, [
            'title' => $input['title'],
            'content' => $input['content'],
            'user_id' => $user->id,
        ], $request->file('thumbnail'));

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

        $this->savePost($post, [
            'title' => $input['title'],
            'content' => $input['content'],
        ], $request->file('thumbnail'));

        return response()->json([
            'message' => 'Cập nhật bài viết thành công',
            'data' => new PostResource($post),
        ], 200);
    }

    /**
     * @param  array{title: string, content: string, user_id?: int}  $attributes
     */
    private function savePost(Post $post, array $attributes, ?UploadedFile $thumbnail): Post
    {
        $newPath = null;
        $oldPath = $post->thumbnail;

        try {
            if ($thumbnail !== null) {
                $newPath = $thumbnail->store('posts/thumbnails', 'public');

                if ($newPath === false) {
                    throw new RuntimeException('Unable to store post thumbnail.');
                }

                $attributes['thumbnail'] = $newPath;
            }

            DB::transaction(function () use ($post, $attributes): void {
                $post->fill($attributes);

                if (! $post->save()) {
                    throw new RuntimeException('Unable to save post.');
                }
            });
        } catch (Throwable $exception) {
            if (is_string($newPath)) {
                Storage::disk('public')->delete($newPath);
            }

            throw $exception;
        }

        if ($newPath !== null && $oldPath !== null && preg_match('#^posts/thumbnails/[A-Za-z0-9_-]+\.(jpe?g|png|webp)$#D', $oldPath)) {
            DB::afterCommit(fn () => Storage::disk('public')->delete($oldPath));
        }

        return $post;
    }
}
