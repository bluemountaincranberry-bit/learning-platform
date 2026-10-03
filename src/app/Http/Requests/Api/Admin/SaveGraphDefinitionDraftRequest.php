<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveGraphDefinitionDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'nodes' => ['required', 'array', 'min:1'],
            'nodes.*.key' => ['required', 'string'],
            'nodes.*.node' => ['required', 'string'],
            'nodes.*.x' => ['sometimes', 'numeric'],
            'nodes.*.y' => ['sometimes', 'numeric'],
            'edges' => ['present', 'array'],
            'edges.*.from' => ['required', 'string'],
            'edges.*.to' => ['required', 'string'],
            'edges.*.label' => ['nullable', 'string'],
        ];
    }
}
