<?php

namespace App\Modules\Srs\Application;

use App\Modules\Content\Domain\Models\ContentLexemeCandidate;
use Illuminate\Support\Facades\DB;

/**
 * Read-only mapping audit for the legacy `type:text` SRS identity.
 *
 * A card is resolvable only when its source content has exactly one matching
 * occurrence and that occurrence has a canonical lexeme. We deliberately do
 * not guess from text alone: the legacy key is not language-safe.
 */
final class LegacyLexemeCardPreflight
{
    /** @return array{cards:int,resolved:int,resolved_mappings:list<array<string,mixed>>,unresolved:list<array<string,mixed>>,collision_audit:list<array<string,mixed>>} */
    public function report(): array
    {
        $cards = DB::table('srs_cards')->orderBy('id')->get(['id', 'user_id', 'content_id', 'item_key', 'lexeme_id']);
        $resolved = 0;
        $resolvedMappings = [];
        $unresolved = [];
        $legacyKeys = [];
        $resolvedConfidenceMappings = [];
        $resolvedProgressMappings = [];

        foreach ($cards as $card) {
            $mapping = $card->lexeme_id !== null
                ? $this->resolveCanonicalCard($card)
                : $this->resolve((int) $card->content_id, (string) $card->item_key);
            if ($card->item_key !== null) {
                $legacyKeys[(string) $card->item_key]['card_ids'][] = (int) $card->id;
                $legacyKeys[(string) $card->item_key]['parsed'] = $mapping['parsed'];
            }

            if ($mapping['lexeme_id'] !== null) {
                $reviewContext = DB::table('srs_reviews')
                    ->leftJoin('content_lexemes', 'content_lexemes.id', '=', 'srs_reviews.content_lexeme_id')
                    ->where('srs_reviews.srs_card_id', $card->id)
                    ->whereNotNull('srs_reviews.content_lexeme_id')
                    ->where(function ($query) use ($mapping): void {
                        $query->whereNull('content_lexemes.id')
                            ->orWhereNull('content_lexemes.lexeme_id')
                            ->orWhere('content_lexemes.lexeme_id', '<>', $mapping['lexeme_id']);
                    })
                    ->select('srs_reviews.id', 'srs_reviews.content_lexeme_id', 'content_lexemes.lexeme_id')
                    ->get();

                if ($reviewContext->isEmpty()) {
                    $resolved++;
                    $resolvedMappings[] = [
                        'card_id' => (int) $card->id,
                        'user_id' => (int) $card->user_id,
                        'content_id' => $card->content_id === null ? null : (int) $card->content_id,
                        'item_key' => $card->item_key === null ? null : (string) $card->item_key,
                        'lexeme_id' => $mapping['lexeme_id'],
                        'content_lexeme_id' => $mapping['source_occurrence_ids'][0] ?? null,
                        'already_canonical' => $card->lexeme_id !== null,
                    ];

                    continue;
                }

                $mapping['reason'] = $reviewContext->contains(fn ($review): bool => $review->lexeme_id === null)
                    ? 'review_context_unresolved'
                    : 'review_context_conflicts_with_card';
                $mapping['conflicting_review_ids'] = $reviewContext->pluck('id')->map(fn ($id): int => (int) $id)->all();
            }

            $unresolved[] = [
                'card_id' => (int) $card->id,
                'content_id' => (int) $card->content_id,
                'item_key' => (string) $card->item_key,
                'reason' => $mapping['reason'],
                'candidate_lexeme_ids' => $mapping['candidate_lexeme_ids'],
                'source_occurrence_ids' => $mapping['source_occurrence_ids'],
                'conflicting_review_ids' => $mapping['conflicting_review_ids'] ?? [],
            ];
        }

        foreach (DB::table('user_lexeme_confidences')->orderBy('id')->get() as $confidence) {
            $mapping = $this->resolveConfidence($confidence);
            if ($mapping['lexeme_id'] !== null) {
                $resolvedConfidenceMappings[] = $mapping;

                continue;
            }

            $unresolved[] = [
                'record_type' => 'confidence',
                'confidence_id' => (int) $confidence->id,
                'user_id' => (int) $confidence->user_id,
                'content_lexeme_id' => $confidence->content_lexeme_id === null ? null : (int) $confidence->content_lexeme_id,
                'reason' => $mapping['reason'],
                'candidate_lexeme_ids' => $mapping['candidate_lexeme_ids'],
            ];
        }

        foreach (DB::table('user_lexeme_progress')->orderBy('id')->get() as $progress) {
            $mapping = $this->resolveProgress($progress);
            if ($mapping['lexeme_id'] !== null) {
                $resolvedProgressMappings[] = $mapping;

                continue;
            }

            $unresolved[] = [
                'record_type' => 'progress',
                'progress_id' => (int) $progress->id,
                'user_id' => (int) $progress->user_id,
                'content_lexeme_id' => $progress->content_lexeme_id === null ? null : (int) $progress->content_lexeme_id,
                'reason' => $mapping['reason'],
                'candidate_lexeme_ids' => $mapping['candidate_lexeme_ids'],
            ];
        }

        return [
            'cards' => $cards->count(),
            'resolved' => $resolved,
            'resolved_mappings' => $resolvedMappings,
            'confidence_rows' => DB::table('user_lexeme_confidences')->count(),
            'resolved_confidence_mappings' => $resolvedConfidenceMappings,
            'progress_rows' => DB::table('user_lexeme_progress')->count(),
            'resolved_progress_mappings' => $resolvedProgressMappings,
            'unresolved' => $unresolved,
            'collision_audit' => $this->collisionAudit($legacyKeys),
        ];
    }

    /** @return array{lexeme_id:?int,reason:?string,candidate_lexeme_ids:list<int>,source_occurrence_ids:list<int>,parsed:array{type:?string,text:?string}} */
    private function resolveCanonicalCard(object $card): array
    {
        $lexeme = DB::table('lexemes')->where('id', $card->lexeme_id)->first(['id', 'owner_user_id']);
        if ($lexeme === null || ($lexeme->owner_user_id !== null && (int) $lexeme->owner_user_id !== (int) $card->user_id)) {
            return $this->unresolved('canonical_card_lexeme_not_visible_to_owner', null, null);
        }

        return [
            'lexeme_id' => (int) $card->lexeme_id,
            'reason' => null,
            'candidate_lexeme_ids' => [(int) $card->lexeme_id],
            'source_occurrence_ids' => [],
            'parsed' => ['type' => null, 'text' => null],
        ];
    }

    /** @return array{progress_id:int,user_id:int,content_lexeme_id:?int,lexeme_id:?int,reason:?string,candidate_lexeme_ids:list<int>} */
    private function resolveProgress(object $progress): array
    {
        if ($progress->content_lexeme_id === null) {
            if ($progress->lexeme_id !== null) {
                return ['progress_id' => (int) $progress->id, 'user_id' => (int) $progress->user_id, 'content_lexeme_id' => null, 'lexeme_id' => (int) $progress->lexeme_id, 'reason' => null, 'candidate_lexeme_ids' => [(int) $progress->lexeme_id]];
            }

            return ['progress_id' => (int) $progress->id, 'user_id' => (int) $progress->user_id, 'content_lexeme_id' => null, 'lexeme_id' => null, 'reason' => 'progress_has_no_source_occurrence', 'candidate_lexeme_ids' => []];
        }

        $source = DB::table('content_lexemes')
            ->join('contents', 'contents.id', '=', 'content_lexemes.content_id')
            ->leftJoin('lexemes', 'lexemes.id', '=', 'content_lexemes.lexeme_id')
            ->where('content_lexemes.id', $progress->content_lexeme_id)
            ->first(['content_lexemes.lexeme_id', 'contents.language as content_language', 'lexemes.language as lexeme_language']);

        $candidateIds = $source?->lexeme_id === null ? [] : [(int) $source->lexeme_id];
        $reason = $source === null || $source->lexeme_id === null
            ? 'progress_source_has_no_canonical_lexeme'
            : (($source->content_language === null || $source->lexeme_language === null || strtolower($source->content_language) !== strtolower($source->lexeme_language))
                ? 'progress_source_language_mismatch' : null);

        return [
            'progress_id' => (int) $progress->id,
            'user_id' => (int) $progress->user_id,
            'content_lexeme_id' => (int) $progress->content_lexeme_id,
            'lexeme_id' => $reason === null ? (int) $source->lexeme_id : null,
            'reason' => $reason,
            'candidate_lexeme_ids' => $candidateIds,
        ];
    }

    /** @return array{lexeme_id:?int,reason:?string,candidate_lexeme_ids:list<int>,source_occurrence_ids:list<int>,parsed:array{type:?string,text:?string}} */
    private function resolve(int $contentId, string $itemKey): array
    {
        if (! str_contains($itemKey, ':')) {
            return $this->unresolved('legacy_key_has_no_type', null, null);
        }

        [$type, $text] = explode(':', $itemKey, 2);
        if (! in_array($type, ContentLexemeCandidate::TYPES, true) || $text === '') {
            return $this->unresolved('unsupported_legacy_key', $type, $text);
        }

        $occurrences = DB::table('content_lexemes')
            ->join('contents', 'contents.id', '=', 'content_lexemes.content_id')
            ->leftJoin('lexemes', 'lexemes.id', '=', 'content_lexemes.lexeme_id')
            ->where('content_lexemes.content_id', $contentId)
            ->where('content_lexemes.type', $type)
            ->where('content_lexemes.text', $text)
            ->orderBy('content_lexemes.id')
            ->get([
                'content_lexemes.id as content_lexeme_id',
                'content_lexemes.lexeme_id',
                'contents.language as content_language',
                'lexemes.language as lexeme_language',
            ]);
        $occurrenceIds = $occurrences->pluck('content_lexeme_id')->map(static fn ($id): int => (int) $id)->all();
        $candidateIds = $occurrences->pluck('lexeme_id')->filter()->unique()->sort()->values()->map(static fn ($id): int => (int) $id)->all();

        if ($occurrences->count() !== 1) {
            return [
                ...$this->unresolved($occurrences->isEmpty() ? 'no_source_occurrence' : 'ambiguous_source_occurrence', $type, $text),
                'candidate_lexeme_ids' => $candidateIds,
                'source_occurrence_ids' => $occurrenceIds,
            ];
        }

        $occurrence = $occurrences->first();
        if ($occurrence->lexeme_id === null) {
            return [
                ...$this->unresolved('no_canonical_occurrence', $type, $text),
                'source_occurrence_ids' => $occurrenceIds,
            ];
        }

        if ($occurrence->content_language === null || $occurrence->lexeme_language === null || strtolower($occurrence->content_language) !== strtolower($occurrence->lexeme_language)) {
            return [
                ...$this->unresolved('unknown_or_mismatched_language', $type, $text),
                'candidate_lexeme_ids' => $candidateIds,
                'source_occurrence_ids' => $occurrenceIds,
            ];
        }

        return [
            'lexeme_id' => (int) $occurrence->lexeme_id,
            'reason' => null,
            'candidate_lexeme_ids' => $candidateIds,
            'source_occurrence_ids' => $occurrenceIds,
            'parsed' => ['type' => $type, 'text' => $text],
        ];
    }

    /** @return array{confidence_id:int,user_id:int,content_lexeme_id:?int,lexeme_id:?int,reason:?string,candidate_lexeme_ids:list<int>} */
    private function resolveConfidence(object $confidence): array
    {
        if ($confidence->lexeme_id !== null) {
            return [
                'confidence_id' => (int) $confidence->id,
                'user_id' => (int) $confidence->user_id,
                'content_lexeme_id' => $confidence->content_lexeme_id === null ? null : (int) $confidence->content_lexeme_id,
                'lexeme_id' => (int) $confidence->lexeme_id,
                'reason' => null,
                'candidate_lexeme_ids' => [(int) $confidence->lexeme_id],
            ];
        }

        if ($confidence->content_lexeme_id === null) {
            return [
                'confidence_id' => (int) $confidence->id,
                'user_id' => (int) $confidence->user_id,
                'content_lexeme_id' => null,
                'lexeme_id' => null,
                'reason' => 'confidence_has_no_source_occurrence',
                'candidate_lexeme_ids' => [],
            ];
        }

        $source = DB::table('content_lexemes')
            ->join('contents', 'contents.id', '=', 'content_lexemes.content_id')
            ->leftJoin('lexemes', 'lexemes.id', '=', 'content_lexemes.lexeme_id')
            ->where('content_lexemes.id', $confidence->content_lexeme_id)
            ->first([
                'content_lexemes.lexeme_id',
                'contents.language as content_language',
                'lexemes.language as lexeme_language',
            ]);

        if ($source === null || $source->lexeme_id === null) {
            $reason = 'confidence_source_has_no_canonical_lexeme';
            $candidateIds = [];
        } elseif ($source->content_language === null || $source->lexeme_language === null || strtolower($source->content_language) !== strtolower($source->lexeme_language)) {
            $reason = 'confidence_source_language_mismatch';
            $candidateIds = [(int) $source->lexeme_id];
        } else {
            $reason = null;
            $candidateIds = [(int) $source->lexeme_id];
        }

        return [
            'confidence_id' => (int) $confidence->id,
            'user_id' => (int) $confidence->user_id,
            'content_lexeme_id' => (int) $confidence->content_lexeme_id,
            'lexeme_id' => $reason === null ? (int) $source->lexeme_id : null,
            'reason' => $reason,
            'candidate_lexeme_ids' => $candidateIds,
        ];
    }

    /** @return array{lexeme_id:null,reason:string,candidate_lexeme_ids:list<int>,source_occurrence_ids:list<int>,parsed:array{type:?string,text:?string}} */
    private function unresolved(string $reason, ?string $type, ?string $text): array
    {
        return [
            'lexeme_id' => null,
            'reason' => $reason,
            'candidate_lexeme_ids' => [],
            'source_occurrence_ids' => [],
            'parsed' => ['type' => $type, 'text' => $text],
        ];
    }

    /** @param array<string,array{card_ids:list<int>,parsed:array{type:?string,text:?string}}> $legacyKeys
     * @return list<array<string,mixed>>
     */
    private function collisionAudit(array $legacyKeys): array
    {
        $audit = [];

        foreach ($legacyKeys as $itemKey => $entry) {
            $type = $entry['parsed']['type'];
            $text = $entry['parsed']['text'];
            $occurrences = collect();
            $learningReferences = ['progress' => [], 'confidence' => [], 'answers' => []];

            if ($type !== null && $text !== null && in_array($type, ContentLexemeCandidate::TYPES, true)) {
                $occurrences = DB::table('content_lexemes')
                    ->join('contents', 'contents.id', '=', 'content_lexemes.content_id')
                    ->leftJoin('lexemes', 'lexemes.id', '=', 'content_lexemes.lexeme_id')
                    ->where('content_lexemes.type', $type)
                    ->where('content_lexemes.text', $text)
                    ->orderBy('content_lexemes.id')
                    ->get([
                        'content_lexemes.id as content_lexeme_id',
                        'content_lexemes.content_id',
                        'content_lexemes.lexeme_id',
                        'contents.language as content_language',
                        'lexemes.language as lexeme_language',
                    ]);
                $occurrenceIds = $occurrences->pluck('content_lexeme_id')->all();
                if ($occurrenceIds !== []) {
                    $learningReferences['progress'] = DB::table('user_lexeme_progress')
                        ->whereIn('content_lexeme_id', $occurrenceIds)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
                    $learningReferences['confidence'] = DB::table('user_lexeme_confidences')
                        ->whereIn('content_lexeme_id', $occurrenceIds)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
                }
                $learningReferences['answers'] = DB::table('learning_answers')
                    ->join('learning_sessions', 'learning_sessions.id', '=', 'learning_answers.learning_session_id')
                    ->where('learning_answers.item_key', $itemKey)
                    ->orderBy('learning_answers.id')
                    ->get(['learning_answers.id', 'learning_answers.learning_session_id', 'learning_sessions.content_id', 'learning_answers.item_key', 'learning_answers.result', 'learning_answers.score', 'learning_answers.answered_at'])
                    ->map(fn ($row): array => (array) $row)->all();
            }

            $audit[] = [
                'item_key' => $itemKey,
                'card_ids' => $entry['card_ids'],
                'same_key_occurrences' => $occurrences->map(fn ($row): array => (array) $row)->all(),
                'learning_references' => $learningReferences,
            ];
        }

        return $audit;
    }
}
