<?php

namespace App\Modules\Interview\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InterviewMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['content' => ['required', 'string', 'max:10000']];
    }
}
