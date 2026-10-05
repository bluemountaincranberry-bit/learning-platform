<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\PersonalGrammarRuleWriterInterface;
use App\Modules\Content\Application\Data\PersonalGrammarRuleDraft;
use App\Modules\Content\Domain\GrammarRuleTitle;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExample;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PersonalGrammarRuleWriter implements PersonalGrammarRuleWriterInterface
{
    public function findOwnedPersonalRule(int $ruleId, int $ownerUserId): bool
    {
        return GrammarRule::query()
            ->whereKey($ruleId)
            ->where('owner_user_id', $ownerUserId)
            ->where('status', GrammarRule::STATUS_PERSONAL)
            ->exists();
    }

    public function isPublishedCatalogRule(int $ruleId, string $language): bool
    {
        return GrammarRule::query()
            ->whereKey($ruleId)
            ->whereNull('owner_user_id')
            ->where('status', GrammarRule::STATUS_PUBLISHED)
            ->where('language', $language)
            ->exists();
    }

    public function findPublishedMatch(string $title, string $language): ?int
    {
        $normalized = GrammarRuleTitle::normalize($title);
        if ($normalized === '') {
            return null;
        }

        return GrammarRule::query()
            ->whereNull('owner_user_id')
            ->where('language', $language)
            ->where('normalized_title', $normalized)
            ->where('status', GrammarRule::STATUS_PUBLISHED)
            ->orderBy('id')
            ->value('id');
    }

    public function createFromLesson(PersonalGrammarRuleDraft $draft): int
    {
        return DB::transaction(function () use ($draft): int {
            $rule = GrammarRule::query()->create([
                'topic_id' => null,
                'slug' => 'personal-'.$draft->ownerUserId.'-'.Str::uuid(),
                'language' => $draft->language,
                'title' => $draft->title,
                'status' => GrammarRule::STATUS_PERSONAL,
                'owner_user_id' => $draft->ownerUserId,
                'source_lesson_id' => $draft->sourceLessonId,
                'source_lesson_title' => $draft->sourceLessonTitle,
                'summary' => $draft->summary,
                'body' => $draft->body,
            ]);

            if (trim((string) $draft->example) !== '') {
                GrammarRuleExample::query()->create([
                    'grammar_rule_id' => $rule->id,
                    'language' => $draft->language,
                    'example' => $draft->example,
                    'translation' => $draft->exampleTranslation,
                    'is_primary' => true,
                    'sort_order' => 0,
                    'origin' => 'lesson',
                ]);
            }

            return (int) $rule->id;
        });
    }
}
