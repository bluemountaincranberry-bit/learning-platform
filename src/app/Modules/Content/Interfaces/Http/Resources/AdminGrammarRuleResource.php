<?php

namespace App\Modules\Content\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminGrammarRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'topic_id' => $this->topic_id,
            'topic' => $this->whenLoaded('topic', fn (): array => [
                'id' => $this->topic->id,
                'slug' => $this->topic->slug,
                'name' => $this->topic->name,
            ]),
            'slug' => $this->slug,
            'language' => $this->language,
            'title' => $this->title,
            'status' => $this->status,
            'level' => $this->level,
            'summary' => $this->summary,
            'body' => $this->body,
            'sort_order' => (int) $this->sort_order,
            'examples_count' => $this->whenCounted('examples'),
            'lexemes_count' => $this->whenCounted('lexemes'),
            'linked_content_count' => $this->whenCounted('contentLinks'),
            'coverage_state' => $this->when(isset($this->coverage_state), $this->coverage_state),
            'lexemes' => $this->whenLoaded('lexemes', fn () => $this->lexemes->map(fn ($lexeme): array => [
                'id' => $lexeme->id,
                'slug' => $lexeme->slug,
                'lemma' => $lexeme->lemma,
                'normalized_lemma' => $lexeme->normalized_lemma,
                'part_of_speech' => $lexeme->part_of_speech,
                'status' => $lexeme->status,
                'level' => $lexeme->level,
            ])->values()),
            'examples' => $this->whenLoaded('examples', fn () => $this->examples->map(fn ($example): array => [
                'id' => $example->id,
                'language' => $example->language,
                'example' => $example->example,
                'translation' => $example->translation,
                'is_primary' => (bool) $example->is_primary,
                'sort_order' => (int) $example->sort_order,
            ])->values()),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
            'editor_version' => (int) $this->editor_version,
        ];
    }
}
