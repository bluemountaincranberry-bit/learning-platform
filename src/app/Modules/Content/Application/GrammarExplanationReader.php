<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\GrammarExplanationReaderInterface;
use App\Modules\Content\Application\Data\GrammarExplanationSource;
use App\Modules\Content\Domain\Models\GrammarRule;

final class GrammarExplanationReader implements GrammarExplanationReaderInterface
{
    public function publishedRule(int $ruleId): ?GrammarExplanationSource
    {
        $rule = GrammarRule::query()
            ->where('status', GrammarRule::STATUS_PUBLISHED)
            ->with(['examples' => fn ($query) => $query->limit(3)])
            ->find($ruleId);

        if ($rule === null) {
            return null;
        }

        return new GrammarExplanationSource(
            title: $rule->title,
            level: $rule->level,
            summary: $rule->summary,
            body: $rule->body,
            examples: $rule->examples->map(fn ($example): array => [
                'example' => $example->example,
                'translation' => $example->translation,
            ])->all(),
        );
    }
}
