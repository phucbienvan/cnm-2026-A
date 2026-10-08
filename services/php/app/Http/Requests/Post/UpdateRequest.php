<?php

/**
 * @author Võ Đức Phú
 */

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'   => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'   => 'Tiêu đề không được để trống.',
            'title.max'        => 'Tiêu đề không được vượt quá 255 ký tự.',
            'content.required' => 'Nội dung không được để trống.',
        ];
    }
}
