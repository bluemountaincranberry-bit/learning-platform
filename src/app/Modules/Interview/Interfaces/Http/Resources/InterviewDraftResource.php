<?php

namespace App\Modules\Interview\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Interview\Domain\Models\InterviewAiDraft */
final class InterviewDraftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'payload' => $this->payload,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
