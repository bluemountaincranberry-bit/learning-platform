<?php

namespace App\Modules\Srs\Application;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class LegacySrsCardMigration
{
    public function __construct(private readonly LegacyLexemeCardPreflight $preflight) {}

    /** @return array{report:array<string,mixed>,groups:list<array<string,mixed>>} */
    public function preview(): array
    {
        $report = $this->preflight->report();

        return [
            'report' => $report,
            'card_groups' => $this->groupMappings($report['resolved_mappings']),
            'confidence_groups' => $this->groupConfidenceMappings($report['resolved_confidence_mappings']),
        ];
    }

    public function apply(string $verifiedBackupReference, bool $allowLocalActiveDatabase = false): int
    {
        $environment = (string) config('app.env');
        $localCutoverAllowed = $environment === 'local' && $allowLocalActiveDatabase;

        if (! in_array($environment, ['testing', 'staging'], true) && ! $localCutoverAllowed) {
            throw new RuntimeException('Apply is restricted to testing or an isolated staging copy. For an explicitly approved local cutover, use --allow-local-active with APP_ENV=local.');
        }

        if (trim($verifiedBackupReference) === '') {
            throw new RuntimeException('A verified, restorable backup reference is required before applying this migration.');
        }

        $report = $this->preflight->report();
        if ($report['unresolved'] !== []) {
            throw new RuntimeException('Legacy SRS migration aborted: preflight has unresolved cards. No learner data was changed.');
        }

        if (! Schema::hasColumn('srs_cards', 'lexeme_id') || ! Schema::hasTable('srs_legacy_migration_audits') || ! Schema::hasTable('user_lexeme_sources')) {
            throw new RuntimeException('Canonical SRS identity schema is not installed.');
        }

        $previousRun = DB::table('srs_legacy_migration_audits')->where('status', 'applied')->orderByDesc('id')->first();
        if ($previousRun !== null
            && ! DB::table('srs_cards')->whereNull('lexeme_id')->exists()
            && ! DB::table('user_lexeme_confidences')->whereNull('lexeme_id')->exists()) {
            return (int) $previousRun->id;
        }

        return DB::transaction(function () use ($verifiedBackupReference, $report): int {
            $cards = DB::table('srs_cards')->orderBy('id')->lockForUpdate()->get();
            $confidenceRows = DB::table('user_lexeme_confidences')->orderBy('id')->lockForUpdate()->get();
            $progressRows = DB::table('user_lexeme_progress')->orderBy('id')->lockForUpdate()->get();
            $freshReport = $this->preflight->report();

            if ($freshReport['unresolved'] !== [] || $cards->count() !== $freshReport['cards'] || $confidenceRows->count() !== $freshReport['confidence_rows'] || $progressRows->count() !== $freshReport['progress_rows']) {
                throw new RuntimeException('Legacy SRS migration aborted because source data changed after preflight.');
            }

            $originalCards = $cards->map(static fn ($card): array => (array) $card)->all();
            $originalReviews = DB::table('srs_reviews')->orderBy('id')->get()->map(static fn ($review): array => (array) $review)->all();
            $originalConfidences = $confidenceRows->map(static fn ($row): array => (array) $row)->all();
            $originalProgress = $progressRows->map(static fn ($row): array => (array) $row)->all();
            $groups = $this->groupMappings($freshReport['resolved_mappings']);
            $confidenceGroups = $this->groupConfidenceMappings($freshReport['resolved_confidence_mappings']);
            $relatedLearningBefore = $this->relatedLearningSnapshot($freshReport);
            $mappingsById = collect($freshReport['resolved_mappings'])->keyBy('card_id');
            $createdSourceIds = [];

            foreach ($groups as $group) {
                $rows = $group['cards'];
                $survivorId = $group['survivor_card_id'];
                $latest = collect($rows)
                    ->sort(fn (array $left, array $right): int => [$right['updated_at'] ?? '', (int) $right['id']] <=> [$left['updated_at'] ?? '', (int) $left['id']])
                    ->first();
                $dueDates = collect($rows)->pluck('next_review_at')->filter()->sort()->values();
                $cardIds = collect($rows)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
                $sourceMappings = collect($cardIds)->map(fn (int $cardId): array => $mappingsById->get($cardId))->all();

                foreach ($sourceMappings as $mapping) {
                    if ($mapping['content_lexeme_id'] !== null) {
                        $createdSourceIds[] = $this->persistSource($mapping);
                    }
                }

                DB::table('srs_reviews')->whereIn('srs_card_id', $cardIds)->update(['srs_card_id' => $survivorId]);
                DB::table('srs_cards')->where('id', $survivorId)->update([
                    'lexeme_id' => $group['lexeme_id'],
                    'state' => collect($rows)->contains(fn (array $card): bool => $card['state'] === 'relearning') ? 'relearning' : $latest['state'],
                    'interval_days' => $latest['interval_days'],
                    'ease_factor' => $latest['ease_factor'],
                    'next_review_at' => $dueDates->first() ?? Carbon::now()->toDateTimeString(),
                    'updated_at' => Carbon::now()->toDateTimeString(),
                ]);
                DB::table('srs_cards')->whereIn('id', array_values(array_diff($cardIds, [$survivorId])))->delete();
            }

            $confidenceMappingsById = collect($freshReport['resolved_confidence_mappings'])->keyBy('confidence_id');
            foreach ($confidenceGroups as $group) {
                $rows = $group['rows'];
                $winner = collect($rows)
                    ->sort(fn (array $left, array $right): int => [$right['updated_at'] ?? '', (int) $right['id']] <=> [$left['updated_at'] ?? '', (int) $left['id']])
                    ->first();
                $rowIds = collect($rows)->pluck('id')->map(static fn ($id): int => (int) $id)->all();

                foreach ($rowIds as $rowId) {
                    $mapping = $confidenceMappingsById->get($rowId);
                    if ($mapping['content_lexeme_id'] !== null) {
                        $createdSourceIds[] = $this->persistSource($mapping);
                    }
                }

                $winnerId = (int) $winner['id'];
                DB::table('user_lexeme_confidences')->where('id', $winnerId)->update([
                    'lexeme_id' => $group['lexeme_id'],
                    'content_lexeme_id' => $winner['content_lexeme_id'],
                    'recognition' => $winner['recognition'],
                    'recall' => $winner['recall'],
                    'production' => $winner['production'],
                    'listening' => $winner['listening'],
                    'speaking' => $winner['speaking'],
                    'updated_at' => Carbon::now()->toDateTimeString(),
                ]);
                DB::table('user_lexeme_confidences')->whereIn('id', array_values(array_diff($rowIds, [$winnerId])))->delete();
            }

            // Learned progress is already keyed by canonical lexeme. Preserve
            // each row verbatim and materialize its source encounter so the
            // audit/snapshot retains where the learned state came from.
            foreach ($freshReport['resolved_progress_mappings'] as $mapping) {
                $createdSourceIds[] = $this->persistSource($mapping);
            }

            $postCutoverCards = DB::table('srs_cards')->orderBy('id')->get()->map(static fn ($card): array => (array) $card)->all();
            $postCutoverReviews = DB::table('srs_reviews')->orderBy('id')->get()->map(static fn ($review): array => (array) $review)->all();
            $postCutoverConfidences = DB::table('user_lexeme_confidences')->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all();
            $postCutoverProgress = DB::table('user_lexeme_progress')->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all();
            $relatedLearningAfter = $this->relatedLearningSnapshot($freshReport, $relatedLearningBefore['scope']);
            $createdSourceIds = array_values(array_unique(array_filter($createdSourceIds)));
            $appliedAt = Carbon::now();
            $auditId = DB::table('srs_legacy_migration_audits')->insertGetId([
                'status' => 'applied',
                'backup_reference' => trim($verifiedBackupReference),
                'payload' => json_encode([
                    'cards_before' => $originalCards,
                    'reviews_before' => $originalReviews,
                    'confidences_before' => $originalConfidences,
                    'progress_before' => $originalProgress,
                    'cards_after' => $postCutoverCards,
                    'reviews_after' => $postCutoverReviews,
                    'confidences_after' => $postCutoverConfidences,
                    'progress_after' => $postCutoverProgress,
                    'progress_source_mappings' => $freshReport['resolved_progress_mappings'],
                    'related_learning_before' => $relatedLearningBefore,
                    'related_learning_after' => $relatedLearningAfter,
                    'created_source_ids' => $createdSourceIds,
                    'collision_audit' => $freshReport['collision_audit'],
                ], JSON_THROW_ON_ERROR),
                'applied_at' => $appliedAt,
                'created_at' => $appliedAt,
                'updated_at' => $appliedAt,
            ]);

            return (int) $auditId;
        });
    }

    public function rollback(int $auditId): void
    {
        DB::transaction(function () use ($auditId): void {
            $audit = DB::table('srs_legacy_migration_audits')->where('id', $auditId)->lockForUpdate()->first();
            if ($audit === null || $audit->status !== 'applied') {
                throw new RuntimeException('No applied legacy SRS migration audit exists for that ID.');
            }

            $payload = json_decode($audit->payload, true, flags: JSON_THROW_ON_ERROR);
            $cardsNow = DB::table('srs_cards')->orderBy('id')->get()->map(static fn ($card): array => (array) $card)->all();
            $reviewsNow = DB::table('srs_reviews')->orderBy('id')->get()->map(static fn ($review): array => (array) $review)->all();
            $confidencesNow = DB::table('user_lexeme_confidences')->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all();
            $progressNow = DB::table('user_lexeme_progress')->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all();
            $relatedLearningNow = $this->relatedLearningSnapshot([], $payload['related_learning_after']['scope']);

            if ($cardsNow !== $payload['cards_after'] || $reviewsNow !== $payload['reviews_after'] || $confidencesNow !== $payload['confidences_after'] || $progressNow !== $payload['progress_after'] || $relatedLearningNow !== $payload['related_learning_after']) {
                throw new RuntimeException('Rollback refused because learner cards, confidence, progress, or review history changed after cutover. Use a forward repair after writes resume.');
            }

            if ($payload['created_source_ids'] !== []) {
                DB::table('user_lexeme_sources')->whereIn('id', $payload['created_source_ids'])->delete();
            }
            DB::table('srs_reviews')->delete();
            DB::table('srs_cards')->delete();
            DB::table('user_lexeme_confidences')->delete();
            foreach ($payload['cards_before'] as $card) {
                DB::table('srs_cards')->insert($card);
            }
            foreach ($payload['reviews_before'] as $review) {
                DB::table('srs_reviews')->insert($review);
            }
            foreach ($payload['confidences_before'] as $confidence) {
                DB::table('user_lexeme_confidences')->insert($confidence);
            }
            DB::table('srs_legacy_migration_audits')->where('id', $auditId)->update([
                'status' => 'rolled_back',
                'updated_at' => Carbon::now(),
            ]);
        });
    }

    /** @param list<array<string,mixed>> $mappings
     *  @return list<array<string,mixed>>
     */
    private function groupMappings(array $mappings): array
    {
        $cards = DB::table('srs_cards')->orderBy('id')->get()->keyBy('id');
        $groups = [];
        foreach ($mappings as $mapping) {
            $key = $mapping['user_id'].':'.$mapping['lexeme_id'];
            $card = $cards->get($mapping['card_id']);
            if ($card === null) {
                throw new RuntimeException('Legacy SRS migration aborted because card identity changed after preflight.');
            }

            $changedIdentity = ($mapping['already_canonical'] ?? false)
                ? (int) $card->lexeme_id !== (int) $mapping['lexeme_id']
                : ((int) $card->content_id !== (int) $mapping['content_id'] || (string) $card->item_key !== $mapping['item_key']);
            if ($changedIdentity) {
                throw new RuntimeException('Legacy SRS migration aborted because card identity changed after preflight.');
            }
            $groups[$key] ??= ['user_id' => (int) $mapping['user_id'], 'lexeme_id' => (int) $mapping['lexeme_id'], 'cards' => []];
            $groups[$key]['cards'][] = (array) $card;
        }

        return array_values(array_map(static function (array $group): array {
            $rows = $group['cards'];
            usort($rows, static fn (array $left, array $right): int => (int) $left['id'] <=> (int) $right['id']);
            $group['cards'] = $rows;
            $group['survivor_card_id'] = (int) $rows[0]['id'];

            return $group;
        }, $groups));
    }

    /** @param list<array<string,mixed>> $mappings
     *  @return list<array<string,mixed>>
     */
    private function groupConfidenceMappings(array $mappings): array
    {
        $rows = DB::table('user_lexeme_confidences')->orderBy('id')->get()->keyBy('id');
        $groups = [];
        foreach ($mappings as $mapping) {
            $key = $mapping['user_id'].':'.$mapping['lexeme_id'];
            $row = $rows->get($mapping['confidence_id']);
            if ($row === null || (int) $row->user_id !== (int) $mapping['user_id'] || $row->content_lexeme_id !== $mapping['content_lexeme_id']) {
                throw new RuntimeException('Legacy confidence migration aborted because source data changed after preflight.');
            }
            $groups[$key] ??= ['user_id' => (int) $mapping['user_id'], 'lexeme_id' => (int) $mapping['lexeme_id'], 'rows' => []];
            $groups[$key]['rows'][] = (array) $row;
        }

        return array_values($groups);
    }

    /** @param array<string,mixed> $mapping */
    private function persistSource(array $mapping): ?int
    {
        $occurrence = DB::table('content_lexemes')
            ->join('contents', 'contents.id', '=', 'content_lexemes.content_id')
            ->where('content_lexemes.id', $mapping['content_lexeme_id'])
            ->first(['content_lexemes.id', 'content_lexemes.text', 'contents.title']);
        if ($occurrence === null) {
            throw new RuntimeException('Legacy SRS migration aborted because a source occurrence disappeared.');
        }

        $existing = DB::table('user_lexeme_sources')
            ->where('user_id', $mapping['user_id'])
            ->where('content_lexeme_id', $mapping['content_lexeme_id'])
            ->first();
        if ($existing !== null) {
            if ((int) $existing->lexeme_id !== (int) $mapping['lexeme_id']) {
                throw new RuntimeException('Legacy SRS migration aborted because an existing source points to another canonical lexeme.');
            }

            return null;
        }

        $now = Carbon::now();

        return (int) DB::table('user_lexeme_sources')->insertGetId([
            'user_id' => $mapping['user_id'],
            'lexeme_id' => $mapping['lexeme_id'],
            'source_kind' => 'content',
            'content_lexeme_id' => $mapping['content_lexeme_id'],
            'lesson_lexeme_candidate_id' => null,
            'source_text' => $occurrence->text,
            'source_example' => null,
            'display_label_snapshot' => $occurrence->title,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string,mixed> $report @param array<string,list<int|string>>|null $scope */
    private function relatedLearningSnapshot(array $report, ?array $scope = null): array
    {
        // ADR-010 requires a complete recovery inventory, including old rows
        // that cannot be joined to a currently resolved card/source mapping.
        // Snapshot each learner-history table in full so unmatched/orphaned
        // activity is still auditable and rollback can detect any concurrent
        // writes. This command is run against a verified restored copy first.
        $scope ??= [
            'user_ids' => DB::table('users')->orderBy('id')->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
            'lexeme_ids' => DB::table('lexemes')->orderBy('id')->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
            'occurrence_ids' => DB::table('content_lexemes')->orderBy('id')->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
            'content_ids' => DB::table('contents')->orderBy('id')->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
            'item_keys' => DB::table('srs_cards')->whereNotNull('item_key')->distinct()->orderBy('item_key')->pluck('item_key')->all(),
        ];
        $rows = [];
        foreach (['user_lexeme_progress', 'user_lexeme_skips', 'user_lexeme_context_checks'] as $table) {
            $rows[$table] = DB::table($table)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all();
        }
        foreach (['user_lexeme_sources', 'exercise_attempts', 'user_lexeme_confidences', 'srs_cards'] as $table) {
            $rows[$table] = DB::table($table)->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all();
        }
        $rows['learning_answers'] = DB::table('learning_answers')
            ->join('learning_sessions', 'learning_sessions.id', '=', 'learning_answers.learning_session_id')
            ->orderBy('learning_answers.id')->get(['learning_answers.*'])->map(static fn ($row): array => (array) $row)->all();
        $rows['srs_reviews'] = DB::table('srs_reviews')->orderBy('id')->get()->map(static fn ($row): array => (array) $row)->all();

        $primaryKeys = collect($rows)->map(fn (array $tableRows): array => collect($tableRows)->pluck('id')->map(static fn ($id): int => (int) $id)->all())->all();
        $counts = collect($rows)->map(fn (array $tableRows): int => count($tableRows))->all();

        return ['scope' => $scope, 'counts' => $counts, 'primary_keys' => $primaryKeys, 'rows' => $rows];
    }
}
