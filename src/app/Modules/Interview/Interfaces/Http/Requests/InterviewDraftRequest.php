<?php

namespace App\Modules\Interview\Interfaces\Http\Requests;

use App\Modules\Interview\Application\InterviewQuestionDraftRules;
use Illuminate\Foundation\Http\FormRequest;

class InterviewDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', 'in:question'],
        ] + InterviewQuestionDraftRules::rules('payload.');
    }
}
