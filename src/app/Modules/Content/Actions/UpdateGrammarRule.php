<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Application\Support\UniqueSlugResolver;
use App\Modules\Content\Domain\Models\GrammarRule;
use Illuminate\Support\Facades\DB;

final class UpdateGrammarRule
{
    public function __construct(
        private readonly UniqueSlugResolver $slugResolver,
        private readonly SyncGrammarRuleRelations $syncRelations,
    ) {}

    public function execute(GrammarRule $rule, array $ruleData): GrammarRule
    {
        DB::transaction(function () use ($rule, $ruleData): void {
            $rule->fill([
                'topic_id' => $ruleData['topic_id'] ?? $rule->topic_id,
                'slug' => array_key_exists('slug', $ruleData)
                    ? $this->slugResolver->resolve(GrammarRule::class, $ruleData['slug'], $ruleData['title'] ?? $rule->title, (int) $rule->id)
                    : $rule->slug,
                'language' => $ruleData['language'] ?? $rule->language,
                'title' => $ruleData['title'] ?? $rule->title,
                'status' => $ruleData['status'] ?? $rule->status,
                'level' => $ruleData['level'] ?? $rule->level,
                'summary' => array_key_exists('summary', $ruleData) ? $ruleData['summary'] : $rule->summary,
                'body' => array_key_exists('body', $ruleData) ? $ruleData['body'] : $rule->body,
                'sort_order' => $ruleData['sort_order'] ?? $rule->sort_order,
            ])->save();

            if (array_key_exists('examples', $ruleData)) {
                $this->syncRelations->examples($rule, $ruleData['examples'] ?? []);
            }

            if (array_key_exists('lexeme_ids', $ruleData)) {
                $this->syncRelations->lexemes($rule, $ruleData['lexeme_ids'] ?? []);
            }
        });

        return $rule->fresh();
    }
}
