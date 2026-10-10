<?php

namespace App\Modules\Interview\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Interview\Domain\Models\InterviewQuestion */
final class InterviewQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prompt_en' => $this->prompt_en,
            'prompt_ru' => $this->prompt_ru,
            'preparation_state' => $this->preparation_state,
            'topic' => $this->whenLoaded('topic', fn ($topic) => $topic === null ? null : InterviewTopicResource::make($topic)),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')->values()),
            'answers' => $this->whenLoaded('answers', fn () => $this->answers->keyBy('kind')->map(fn ($answer) => [
                'id' => $answer->id,
                'en' => $answer->text_en,
                'ru' => $answer->text_ru,
                'revisions' => $answer->relationLoaded('revisions')
                    ? $answer->revisions->map(fn ($revision) => [
                        'id' => $revision->id,
                        'text_en' => $revision->text_en,
                        'text_ru' => $revision->text_ru,
                        'created_at' => $revision->created_at,
                    ])->values()
                    : [],
            ])),
        ];
    }
}
