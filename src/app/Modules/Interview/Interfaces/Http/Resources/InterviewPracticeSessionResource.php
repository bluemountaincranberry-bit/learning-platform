<?php

namespace App\Modules\Interview\Interfaces\Http\Resources;

use App\Modules\Interview\Application\InterviewPracticeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Modules\Interview\Domain\Models\InterviewPracticeSession */
final class InterviewPracticeSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $practice = app(InterviewPracticeService::class);
        $questions = $practice->questionsForSession($this->resource);
        $profile = $practice->profileForSession($this->resource);

        return [
            'id' => $this->id,
            'conversation_id' => $this->agent_conversation_id,
            'mode' => $this->mode,
            'status' => $this->status,
            'question_count' => $this->question_count,
            'focus' => $this->focus,
            'difficulty' => $this->difficulty,
            'questions' => $questions->map(fn ($question) => (new InterviewQuestionResource($question))->toArray($request))->values(),
            'profile' => $profile === null ? null : (new InterviewProfileResource($profile))->toArray($request),
            'messages' => $practice->history($this->resource),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
