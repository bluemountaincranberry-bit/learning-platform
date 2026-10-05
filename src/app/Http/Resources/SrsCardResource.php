<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SrsCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'lexeme_id' => $this->lexeme_id,
            'content_id' => $this->content_id,
            'item_key' => $this->item_key,
            'lexeme_display' => $this->lexemeDisplay(),
            'state' => $this->state,
            'interval_days' => $this->interval_days,
            'ease_factor' => $this->ease_factor,
            'next_review_at' => $this->resource->next_review_at instanceof \DateTimeInterface ? $this->resource->next_review_at->toIso8601String() : $this->next_review_at,
            'created_at' => $this->resource->created_at instanceof \DateTimeInterface ? $this->resource->created_at->toIso8601String() : $this->created_at,
            'updated_at' => $this->resource->updated_at instanceof \DateTimeInterface ? $this->resource->updated_at->toIso8601String() : $this->updated_at,
        ];
    }

    /**
     * Human-readable lexeme text for display (e.g. on repetition cards).
     * Uses joined content_lexemes.text when available; otherwise strips "word:" / "phrase:" from item_key.
     */
    private function lexemeDisplay(): string
    {
        if (isset($this->lexeme_display) && (string) $this->lexeme_display !== '') {
            return (string) $this->lexeme_display;
        }

        return (string) preg_replace('/^(word|phrase):/', '', $this->item_key ?? '');
    }
}
