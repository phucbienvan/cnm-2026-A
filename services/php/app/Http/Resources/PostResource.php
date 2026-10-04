<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    /**
     * Chuyển đổi resource Post thành mảng dữ liệu phản hồi JSON.
     *
     * @return array{id: mixed, title: mixed, content: mixed}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'content' => $this->resource->content,
        ];
    }
}

