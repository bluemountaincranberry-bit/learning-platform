<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\EmbeddingSourceReaderInterface;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\Lexeme;

class EmbeddingSourceReader implements EmbeddingSourceReaderInterface
{
    public function contentLexemes(array $ids): array
    {
        return ContentLexeme::query()->whereIn('id', $ids)->get(['id', 'text'])
            ->map(fn (ContentLexeme $lexeme): array => ['id' => $lexeme->id, 'text' => $lexeme->text])->all();
    }

    public function canonicalLexemes(array $ids): array
    {
        return Lexeme::query()->whereIn('id', $ids)->get(['id', 'lemma'])
            ->map(fn (Lexeme $lexeme): array => ['id' => $lexeme->id, 'text' => $lexeme->lemma])->all();
    }

    public function grammarRules(array $ids): array
    {
        // VIK-16: archived rules are merged-away duplicates; never re-embed them.
        return GrammarRule::query()->whereNull('owner_user_id')->whereIn('id', $ids)->where('status', '!=', GrammarRule::STATUS_ARCHIVED)->get(['id', 'title', 'summary'])
            ->map(fn (GrammarRule $rule): array => ['id' => $rule->id, 'text' => trim($rule->title.' '.$rule->summary)])->all();
    }
}
