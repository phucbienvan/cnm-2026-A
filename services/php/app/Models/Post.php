<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['title', 'content', 'user_id', 'thumbnail'])]
class Post extends Model
{
    use SoftDeletes;

    /**
     * Get full URL for thumbnail (fallback to placeholder)
     */
    public function thumbnailUrl()
    {
        return $this->thumbnail
            ? asset('storage/' . $this->thumbnail)
            : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
