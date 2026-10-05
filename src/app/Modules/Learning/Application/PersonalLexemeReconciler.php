<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\PersonalLexemeReconcilerInterface;
use App\Modules\Srs\Application\Contracts\PersonalLexemeCardMergerInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PersonalLexemeReconciler implements PersonalLexemeReconcilerInterface
{
    private const TABLES = [
        'user_lexeme_sources' => 'lexeme_id',
        'user_lexeme_progress' => 'lexeme_id',
        'user_lexeme_confidences' => 'lexeme_id',
        'user_lexeme_skips' => 'lexeme_id',
        'user_lexeme_context_checks' => 'lexeme_id',
        'lesson_lexeme_candidates' => 'matched_lexeme_id',
    ];

    public function __construct(private readonly PersonalLexemeCardMergerInterface $cards) {}

    public function reconcile(int $ownerUserId, int $privateLexemeId, int $sharedLexemeId): void
    {
        DB::transaction(function () use ($ownerUserId, $privateLexemeId, $sharedLexemeId): void {
            $private = DB::table('lexemes')->where('id', $privateLexemeId)->lockForUpdate()->first();
            $shared = DB::table('lexemes')->where('id', $sharedLexemeId)->lockForUpdate()->first();
            if ($private === null || $shared === null || (int) $private->owner_user_id !== $ownerUserId
                || $shared->owner_user_id !== null || strtolower($private->language) !== strtolower($shared->language)
                || mb_strtolower(trim($private->lemma)) !== mb_strtolower(trim($shared->lemma))) {
                throw new RuntimeException('Private lexeme reconciliation requires the owner’s exact same-language shared match.');
            }
            if ($private->alias_to_lexeme_id !== null) {
                if ((int) $private->alias_to_lexeme_id !== $sharedLexemeId) {
                    throw new RuntimeException('Private lexeme already aliases a different shared identity.');
                }

                return;
            }

            $candidateIds = DB::table('lesson_lexeme_candidates')->where('matched_lexeme_id', $privateLexemeId)->orderBy('id')->pluck('id')->all();
            $before = $this->snapshot($ownerUserId, $privateLexemeId, $sharedLexemeId, $candidateIds);
            $this->copyPrivateAnnotationsToSource($ownerUserId, $private);
            $this->mergeSources($ownerUserId, $privateLexemeId, $sharedLexemeId);
            $this->mergeUniqueRows('user_lexeme_progress', $ownerUserId, $privateLexemeId, $sharedLexemeId, 'learned_at');
            $this->mergeUniqueRows('user_lexeme_confidences', $ownerUserId, $privateLexemeId, $sharedLexemeId, 'updated_at');
            $this->mergeUniqueRows('user_lexeme_skips', $ownerUserId, $privateLexemeId, $sharedLexemeId, 'updated_at');
            $this->mergeUniqueRows('user_lexeme_context_checks', $ownerUserId, $privateLexemeId, $sharedLexemeId, 'checked_at');
            DB::table('lesson_lexeme_candidates')->where('matched_lexeme_id', $privateLexemeId)->update(['matched_lexeme_id' => $sharedLexemeId]);
            $this->cards->merge($ownerUserId, $privateLexemeId, $sharedLexemeId);
            DB::table('lexemes')->where('id', $privateLexemeId)->update(['alias_to_lexeme_id' => $sharedLexemeId, 'updated_at' => now()]);
            $after = $this->snapshot($ownerUserId, $privateLexemeId, $sharedLexemeId, $candidateIds);
            DB::table('personal_lexeme_reconciliation_audits')->insert([
                'user_id' => $ownerUserId,
                'private_lexeme_id' => $privateLexemeId,
                'shared_lexeme_id' => $sharedLexemeId,
                'status' => 'applied',
                'payload' => json_encode(['before' => $before, 'after' => $after], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function rollback(int $auditId): void
    {
        DB::transaction(function () use ($auditId): void {
            $audit = DB::table('personal_lexeme_reconciliation_audits')->where('id', $auditId)->lockForUpdate()->first();
            if ($audit === null || $audit->status !== 'applied') {
                throw new RuntimeException('No applied personal lexeme reconciliation audit exists for that ID.');
            }
            $payload = json_decode($audit->payload, true, flags: JSON_THROW_ON_ERROR);
            $now = $this->snapshot((int) $audit->user_id, (int) $audit->private_lexeme_id, (int) $audit->shared_lexeme_id, $payload['before']['lesson_candidate_ids']);
            if ($now !== $payload['after']) {
                throw new RuntimeException('Reconciliation rollback refused because learner state changed after reconciliation.');
            }
            $this->restore((int) $audit->user_id, (int) $audit->private_lexeme_id, (int) $audit->shared_lexeme_id, $payload['before']);
            DB::table('personal_lexeme_reconciliation_audits')->where('id', $auditId)->update(['status' => 'rolled_back', 'updated_at' => now()]);
        });
    }

    private function snapshot(int $userId, int $privateId, int $sharedId, ?array $candidateIds = null): array
    {
        $snapshot = [
            'lexemes' => DB::table('lexemes')->whereIn('id', [$privateId, $sharedId])->orderBy('id')->get()->map(fn ($r): array => (array) $r)->all(),
            'lesson_candidate_ids' => $candidateIds ?? DB::table('lesson_lexeme_candidates')->where('matched_lexeme_id', $privateId)->orderBy('id')->pluck('id')->all(),
        ];
        foreach (self::TABLES as $table => $identityColumn) {
            $query = DB::table($table);
            if ($table === 'lesson_lexeme_candidates') {
                $query->whereIn('id', $snapshot['lesson_candidate_ids']);
            } else {
                $query->where('user_id', $userId)->whereIn($identityColumn, [$privateId, $sharedId]);
            }
            $snapshot[$table] = $query->orderBy('id')->get()->map(fn ($r): array => (array) $r)->all();
        }
        foreach (['srs_cards' => 'lexeme_id', 'srs_reviews' => null] as $table => $column) {
            $query = DB::table($table);
            if ($column === null) {
                $query->whereIn('srs_card_id', DB::table('srs_cards')->select('id')->where('user_id', $userId)->whereIn('lexeme_id', [$privateId, $sharedId]));
            } else {
                $query->where('user_id', $userId)->whereIn($column, [$privateId, $sharedId]);
            }
            $snapshot[$table] = $query->orderBy('id')->get()->map(fn ($r): array => (array) $r)->all();
        }

        return $snapshot;
    }

    private function restore(int $userId, int $privateId, int $sharedId, array $snapshot): void
    {
        $cardIds = DB::table('srs_cards')->where('user_id', $userId)->whereIn('lexeme_id', [$privateId, $sharedId])->pluck('id');
        DB::table('srs_reviews')->whereIn('srs_card_id', $cardIds)->delete();
        DB::table('srs_cards')->where('user_id', $userId)->whereIn('lexeme_id', [$privateId, $sharedId])->delete();
        foreach (['user_lexeme_sources', 'user_lexeme_progress', 'user_lexeme_confidences', 'user_lexeme_skips', 'user_lexeme_context_checks', 'lesson_lexeme_candidates'] as $table) {
            $rows = $snapshot[$table];
            if ($table === 'lesson_lexeme_candidates') {
                DB::table($table)->whereIn('id', $snapshot['lesson_candidate_ids'])->delete();
            } else {
                $column = self::TABLES[$table] ?? 'lexeme_id';
                DB::table($table)->where('user_id', $userId)->whereIn($column, [$privateId, $sharedId])->delete();
            }
            if ($rows !== []) {
                DB::table($table)->insert($rows);
            }
        }
        if ($snapshot['srs_cards'] !== []) {
            DB::table('srs_cards')->insert($snapshot['srs_cards']);
        }
        if ($snapshot['srs_reviews'] !== []) {
            DB::table('srs_reviews')->insert($snapshot['srs_reviews']);
        }
        $private = collect($snapshot['lexemes'])->firstWhere('id', $privateId);
        if ($private !== null) {
            DB::table('lexemes')->where('id', $privateId)->update(['alias_to_lexeme_id' => $private['alias_to_lexeme_id'], 'updated_at' => $private['updated_at']]);
        }
    }

    private function mergeSources(int $userId, int $privateId, int $sharedId): void
    {
        foreach (['manual' => null, 'content' => 'content_lexeme_id', 'lesson' => 'lesson_lexeme_candidate_id'] as $kind => $referenceColumn) {
            $privateSources = DB::table('user_lexeme_sources')->where('user_id', $userId)
                ->where('lexeme_id', $privateId)->where('source_kind', $kind)->orderBy('id')->lockForUpdate()->get();
            foreach ($privateSources as $source) {
                $collision = DB::table('user_lexeme_sources')->where('user_id', $userId)->where('lexeme_id', $sharedId)->where('source_kind', $kind);
                if ($referenceColumn === null) {
                    $existing = $collision->first();
                } elseif ($source->{$referenceColumn} === null) {
                    $existing = null;
                } else {
                    $existing = $collision->where($referenceColumn, $source->{$referenceColumn})->first();
                }
                if ($existing === null) {
                    continue;
                }

                $annotations = collect([$existing->source_example, $source->source_example, $source->source_text])
                    ->filter()->unique()->implode("\n");
                DB::table('user_lexeme_sources')->where('id', $existing->id)->update([
                    'source_example' => $annotations === '' ? null : $annotations,
                    'updated_at' => now(),
                ]);
                DB::table('user_lexeme_sources')->where('id', $source->id)->delete();
            }
        }
        DB::table('user_lexeme_sources')->where('user_id', $userId)->where('lexeme_id', $privateId)->update(['lexeme_id' => $sharedId, 'updated_at' => now()]);
    }

    private function copyPrivateAnnotationsToSource(int $userId, object $privateLexeme): void
    {
        $translations = DB::table('lexeme_translations')->where('lexeme_id', $privateLexeme->id)->orderBy('id')->get();
        $annotations = collect([
            $privateLexeme->notes === null || trim($privateLexeme->notes) === '' ? null : 'Private notes: '.trim($privateLexeme->notes),
            $translations->isEmpty() ? null : 'Private translations: '.$translations->map(fn (object $row): string => $row->language.' — '.$row->translation)->implode('; '),
        ])->filter()->implode("\n");
        if ($annotations === '') {
            return;
        }

        $source = DB::table('user_lexeme_sources')->where('user_id', $userId)->where('lexeme_id', $privateLexeme->id)->where('source_kind', 'manual')->first();
        if ($source === null) {
            DB::table('user_lexeme_sources')->insert([
                'user_id' => $userId,
                'lexeme_id' => $privateLexeme->id,
                'source_kind' => 'manual',
                'source_text' => $privateLexeme->lemma,
                'display_label_snapshot' => 'My words',
                'source_example' => $annotations,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return;
        }

        $existing = $source->source_example === null ? '' : trim($source->source_example);
        DB::table('user_lexeme_sources')->where('id', $source->id)->update([
            'source_example' => collect([$existing, $annotations])->filter()->implode("\n"),
            'updated_at' => now(),
        ]);
    }

    private function mergeUniqueRows(string $table, int $userId, int $privateId, int $sharedId, string $timestamp): void
    {
        $rows = DB::table($table)->where('user_id', $userId)->whereIn('lexeme_id', [$privateId, $sharedId])->orderBy('id')->lockForUpdate()->get();
        if ($rows->count() <= 1) {
            DB::table($table)->where('user_id', $userId)->where('lexeme_id', $privateId)->update(['lexeme_id' => $sharedId]);
            return;
        }
        $winner = $rows->sort(fn (object $a, object $b): int => [$b->{$timestamp} ?? '', (int) $b->id] <=> [$a->{$timestamp} ?? '', (int) $a->id])->first();
        DB::table($table)->where('id', $winner->id)->update(['lexeme_id' => $sharedId, 'updated_at' => now()]);
        DB::table($table)->where('user_id', $userId)->whereIn('lexeme_id', [$privateId, $sharedId])->where('id', '<>', $winner->id)->delete();
    }
}
