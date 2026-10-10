<?php

namespace App\Modules\Interview\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Interview\Domain\Models\InterviewProfile */
final class InterviewProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'career_goal' => $this->career_goal,
            'skills' => $this->skills,
            'experience_level' => $this->experience_level,
            'projects' => $this->projects,
            'experience_stories' => $this->experience_stories,
            'milestones' => $this->whenLoaded('milestones'),
            'observations' => $this->whenLoaded('observations'),
        ];
    }
}
