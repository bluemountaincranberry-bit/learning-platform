<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LearningProgressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'content_id' => $this->content_id,
            'total_answers' => $this->total_answers,
            'known_answers' => $this->known_answers,
            'unknown_answers' => $this->unknown_answers,
            'accuracy' => $this->accuracy,
            'created_at' => $this->resource->created_at instanceof \DateTimeInterface ? $this->resource->created_at->toIso8601String() : $this->created_at,
            'updated_at' => $this->resource->updated_at instanceof \DateTimeInterface ? $this->resource->updated_at->toIso8601String() : $this->updated_at,
        ];
    }
}
