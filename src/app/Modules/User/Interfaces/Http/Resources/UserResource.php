<?php

namespace App\Modules\User\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'timezone' => $this->timezone ?? null,
            'ui_language' => $this->ui_language ?? null,
            'translation_language' => $this->translation_language ?? null,
            'current_level' => $this->current_level ?? null,
            'learning_goal' => $this->learning_goal ?? null,
            'ai_extraction_thoroughness' => $this->ai_extraction_thoroughness ?? null,
            'daily_goal' => $this->daily_goal,
            'email_verified_at' => $this->resource->email_verified_at instanceof \DateTimeInterface ? $this->resource->email_verified_at->toIso8601String() : $this->email_verified_at,
            'created_at' => $this->resource->created_at instanceof \DateTimeInterface ? $this->resource->created_at->toIso8601String() : $this->created_at,
            'updated_at' => $this->resource->updated_at instanceof \DateTimeInterface ? $this->resource->updated_at->toIso8601String() : $this->updated_at,
        ];
    }
}
