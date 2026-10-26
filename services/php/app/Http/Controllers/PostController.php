<?php

namespace App\Http\Controllers;

use App\Http\Requests\Post\CreateRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use RuntimeException;

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
        $thumbnailPath = $this->storeThumbnail($request);

        $data = Post::create([
            'title' => $input['title'],
            'content' => $input['content'],
            'thumbnail' => $thumbnailPath,
            'user_id' => $user->id,
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
        
        $attributes = [
            'title' => $input['title'],
            'content' => $input['content'],
        ];

        if ($request->hasFile('thumbnail')) {
            $attributes['thumbnail'] = $this->storeThumbnail($request);
        }

        $post->update($attributes);

        return response()->json([
            'message' => 'Cập nhật bài viết thành công',
            'data' => new PostResource($post),
        ], 200);
    }

    private function storeThumbnail(CreateRequest $request): ?string
    {
        $thumbnail = $request->file('thumbnail');

        if ($thumbnail === null) {
            return null;
        }

        $path = $thumbnail->store('posts', 'public');

        if ($path === false) {
            throw new RuntimeException('Unable to store post thumbnail.');
        }

        return $path;
    }
}
