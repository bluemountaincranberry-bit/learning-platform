<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Modules\Content\Domain\Models\Content;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentIndexRequest extends FormRequest
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
            'type' => ['sometimes', 'string', Rule::in(Content::TYPES)],
            'language' => ['sometimes', 'string', 'max:10'],
            'level' => ['sometimes', 'string', 'max:4'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'q' => ['sometimes', 'string', 'max:200'],
            // 'mine' scopes the catalog to the requesting user's own submissions
            // (ContentController::index resolves it against the authenticated
            // user — a no-op for guests, who have no "mine" to scope to).
            'scope' => ['sometimes', 'string', Rule::in(['mine', 'all'])],
        ];
    }

    public function getPerPage(): int
    {
        $v = $this->validated();
        $perPage = (int) ($v['per_page'] ?? 15);

        return min(max($perPage, 1), 100);
    }
}
