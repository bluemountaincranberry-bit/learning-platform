<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\RagSourceReaderInterface;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Domain\Models\LexemeExample;

class RagSourceReader implements RagSourceReaderInterface
{
    public function publishedGrammarRules(?array $ids = null): iterable
    {
        $query = GrammarRule::query()->where('status', GrammarRule::STATUS_PUBLISHED);
        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        foreach ($query->cursor() as $rule) {
            yield [
                'id' => $rule->id,
                'language' => $rule->language,
                'level' => $rule->level,
                'title' => $rule->title,
                'summary' => $rule->summary,
                'body' => $rule->body,
            ];
        }
    }

    public function publishedLexemeExamples(?array $ids = null): iterable
    {
        $query = LexemeExample::query()
            ->whereHas('lexeme', fn ($q) => $q->whereNull('owner_user_id')->where('status', Lexeme::STATUS_PUBLISHED))
            ->with('lexeme');
        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        foreach ($query->cursor() as $example) {
            yield [
                'id' => $example->id,
                'language' => $example->language,
                'level' => $example->lexeme->level ?? null,
                'lemma' => $example->lexeme->lemma ?? null,
                'example' => $example->example,
                'translation' => $example->translation,
            ];
        }
    }
}
