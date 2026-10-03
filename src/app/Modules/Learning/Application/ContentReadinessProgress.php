<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\ContentReadinessProgressInterface;
use App\Modules\Learning\Domain\Models\UserGrammarRule;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;

class ContentReadinessProgress implements ContentReadinessProgressInterface
{
    public function learnedCounts(int $userId, array $lexemeIds, array $grammarRuleIds): array
    {
        return [
            'words_learned' => $lexemeIds === [] ? 0 : UserLexemeProgress::query()
                ->where('user_id', $userId)
                ->whereIn('lexeme_id', $lexemeIds)
                ->distinct('lexeme_id')
                ->count('lexeme_id'),
            'grammar_learned' => $grammarRuleIds === [] ? 0 : UserGrammarRule::query()
                ->where('user_id', $userId)
                ->whereIn('grammar_rule_id', $grammarRuleIds)
                ->where('status', UserGrammarRule::STATUS_LEARNED)
                ->count(),
        ];
    }
}
