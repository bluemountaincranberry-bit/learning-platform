<?php

namespace App\Modules\Learning\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LessonIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['status' => ['sometimes', 'in:all,active,archived']];
    }
}
