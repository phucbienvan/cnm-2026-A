<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Bảng tương ứng trong cơ sở dữ liệu.
     *
     * @var string
     */
    protected $table = 'posts';

    /**
     * Các thuộc tính có thể gán giá trị hàng loạt (Mass Assignment).
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'content',
        'status',
    ];
}

