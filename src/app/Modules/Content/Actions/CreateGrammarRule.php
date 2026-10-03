<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Application\Support\UniqueSlugResolver;
use App\Modules\Content\Domain\Models\GrammarRule;
use Illuminate\Support\Facades\DB;

final class CreateGrammarRule
{
    public function __construct(
        private readonly UniqueSlugResolver $slugResolver,
        private readonly SyncGrammarRuleRelations $syncRelations,
    ) {}

    public function execute(array $ruleData): GrammarRule
    {
        return DB::transaction(function () use ($ruleData): GrammarRule {
            $rule = GrammarRule::query()->create([
                'topic_id' => $ruleData['topic_id'],
                'slug' => $this->slugResolver->resolve(GrammarRule::class, $ruleData['slug'] ?? null, $ruleData['title']),
                'language' => $ruleData['language'] ?? 'en',
                'title' => $ruleData['title'],
                'status' => $ruleData['status'] ?? GrammarRule::STATUS_DRAFT,
                'level' => $ruleData['level'] ?? null,
                'summary' => $ruleData['summary'] ?? null,
                'body' => $ruleData['body'] ?? null,
                'sort_order' => $ruleData['sort_order'] ?? 0,
            ]);

            $this->syncRelations->examples($rule, $ruleData['examples'] ?? []);
            $this->syncRelations->lexemes($rule, $ruleData['lexeme_ids'] ?? []);

            return $rule;
        });
    }
}
