<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'thumbnail' => $this->thumbnail,
            'thumbnail_url' => $this->resolveThumbnailUrl(),
            'user_id' => $this->user_id,
            'user' => new UserResource($this->user),
        ];
    }

    /**
     * Trả về URL công khai của thumbnail, không có thumbnail thì trả về null.
     */
    private function resolveThumbnailUrl(): ?string
    {
        if (empty($this->thumbnail)) {
            return null;
        }

        return Storage::disk('public')->url($this->thumbnail);
    }
}