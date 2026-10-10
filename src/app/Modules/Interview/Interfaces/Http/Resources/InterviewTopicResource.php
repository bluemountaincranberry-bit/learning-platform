<?php

namespace App\Modules\Interview\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Interview\Domain\Models\InterviewTopic */
final class InterviewTopicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'sort_order' => $this->sort_order,
        ];
    }
}
