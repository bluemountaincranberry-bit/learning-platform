<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\SentencePracticeCatalogInterface;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use Illuminate\Support\Facades\DB;

final class SentencePracticeCatalog implements SentencePracticeCatalogInterface
{
    public function learningLexemes(int $userId, ?int $contentId = null): array
    {
        $itemKey = in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)
            ? "(content_lexemes.type || ':' || content_lexemes.text)"
            : "CONCAT(content_lexemes.type, ':', content_lexemes.text)";

        return ContentLexeme::query()->with(['canonicalLexeme', 'content'])
            ->whereHas('content', fn ($query) => $query->whereIn('status', Content::PUBLIC_STATUSES))
            ->when($contentId !== null, fn ($query) => $query->where('content_lexemes.content_id', $contentId))
            ->join('srs_cards', function ($join) use ($userId, $itemKey): void {
                $join->on('srs_cards.content_id', '=', 'content_lexemes.content_id')
                    ->where('srs_cards.user_id', $userId)
                    ->whereRaw("{$itemKey} = srs_cards.item_key");
            })
            ->orderBy('content_lexemes.id')
            ->get(['content_lexemes.*', 'srs_cards.state as review_state'])
            ->map(fn (ContentLexeme $occurrence): array => [
                'id' => (int) $occurrence->id,
                'content_id' => (int) $occurrence->content_id,
                'word' => (string) ($occurrence->canonicalLexeme?->lemma ?? $occurrence->text),
                'language' => $occurrence->content?->language,
                'review_state' => $occurrence->review_state,
            ])->all();
    }

    public function grammarRules(int $contentId, ?array $grammarRuleIds = null): array
    {
        return Content::query()->findOrFail($contentId)->grammarRules()
            ->when($grammarRuleIds !== null, fn ($query) => $query->whereIn('grammar_rules.id', $grammarRuleIds))
            ->get(['grammar_rules.id', 'grammar_rules.title'])
            ->map(fn ($rule): array => ['id' => (int) $rule->id, 'title' => (string) $rule->title])
            ->all();
    }

    public function words(int $contentId): array
    {
        return ContentLexeme::query()->with('canonicalLexeme')->where('content_id', $contentId)->get()
            ->map(fn (ContentLexeme $occurrence): string => (string) ($occurrence->canonicalLexeme?->lemma ?? $occurrence->text))
            ->filter()->unique()->values()->all();
    }

    public function language(int $contentId): string
    {
        return (string) Content::query()->findOrFail($contentId)->language;
    }
}
