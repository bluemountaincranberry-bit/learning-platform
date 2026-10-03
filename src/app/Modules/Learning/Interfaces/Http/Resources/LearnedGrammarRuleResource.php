<?php

namespace App\Modules\Learning\Interfaces\Http\Resources;

use App\Modules\Learning\Domain\Models\UserGrammarRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserGrammarRule */
class LearnedGrammarRuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'grammar_rule_id' => $this->grammar_rule_id,
            'title' => $this->rule_title,
            'summary' => $this->rule_summary,
            'level' => $this->rule_level,
            'topic' => $this->rule_topic_id !== null ? [
                'id' => $this->rule_topic_id,
                'name' => $this->rule_topic_name,
            ] : null,
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'learned_at' => $this->learned_at?->toIso8601String(),
        ];
    }
}
