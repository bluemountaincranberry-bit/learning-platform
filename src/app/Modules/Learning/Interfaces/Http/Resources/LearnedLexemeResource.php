<?php

namespace App\Modules\Learning\Interfaces\Http\Resources;

use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserLexemeProgress */
class LearnedLexemeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lexeme_id' => $this->lexeme_id,
            'content_lexeme_id' => $this->content_lexeme_id,
            'lexeme' => $this->lexeme,
            'learned_at' => $this->learned_at?->toIso8601String(),
            'translation' => $this->translation,
            'example' => $this->example,
            'examples' => $this->examples,
            'associations' => $this->associations,
            'in_review' => (bool) $this->in_review,
            'contexts' => $this->contexts,
        ];
    }
}
