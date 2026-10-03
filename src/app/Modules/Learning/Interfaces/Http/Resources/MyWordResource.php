<?php

namespace App\Modules\Learning\Interfaces\Http\Resources;

use App\Modules\Content\Application\Contracts\LexemePresentationReaderInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyWordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $primaryContentId = $this->primary_content_id;
        $translationLanguage = $request->user()?->translation_language ?? config('ai.analysis.translation_language', 'ru');
        $presentation = app(LexemePresentationReaderInterface::class)
            ->fromLoadedLexeme($this->resource, $primaryContentId, $translationLanguage);

        return [
            'id' => $this->id,
            'lexeme_id' => $this->id,
            'content_lexeme_id' => $this->primary_content_lexeme_id,
            'lexeme' => $this->lemma,
            'status' => $this->word_status,
            'learned_at' => $this->learned_at?->toIso8601String(),
            'in_review' => (bool) $this->in_review,
            'language' => $this->language,
            'level' => $this->level,
            'translation' => $presentation['translation'],
            'example' => $presentation['example'],
            'examples' => $presentation['examples'],
            'associations' => $presentation['associations'],
            'contexts' => $presentation['contexts'],
        ];
    }
}
