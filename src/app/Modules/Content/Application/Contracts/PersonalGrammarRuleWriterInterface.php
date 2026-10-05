<?php

namespace App\Modules\Content\Application\Contracts;

use App\Modules\Content\Application\Data\PersonalGrammarRuleDraft;
interface PersonalGrammarRuleWriterInterface
{
    public function findOwnedPersonalRule(int $ruleId, int $ownerUserId): bool;

    public function isPublishedCatalogRule(int $ruleId, string $language): bool;

    public function findPublishedMatch(string $title, string $language): ?int;

    public function createFromLesson(PersonalGrammarRuleDraft $draft): int;
}
