<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Xác định người dùng có quyền thực hiện request này hay không.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Quy tắc validation cho việc cập nhật bài viết.
     *
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string'],
        ];
    }
}

