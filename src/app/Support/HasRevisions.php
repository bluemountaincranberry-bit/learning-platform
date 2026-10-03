<?php

namespace App\Support;

use App\Modules\Infrastructure\Domain\Models\EntityRevision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Generic revision history for any model — not grammar-specific. A model
 * opts in with `use HasRevisions;` and declares which fields are worth
 * logging via `protected array $revisionable = [...]` (skip timestamps,
 * sort_order, and other noise nobody needs a history of).
 *
 * Every actual DB save is attributed to whoever committed it (the admin
 * clicking "Save"), even when the content originated from an AI draft —
 * the AI only ever fills the live edit form (see AiFieldEditService), it
 * never saves directly. `EntityRevision::SOURCE_AI` is reserved for a
 * future fully-autonomous write path, not used by this trait today.
 */
trait HasRevisions
{
    public static function bootHasRevisions(): void
    {
        static::created(function (Model $model): void {
            $changes = [];
            foreach ($model->getRevisionableAttributes() as $field) {
                $changes[$field] = ['old' => null, 'new' => $model->getAttribute($field)];
            }
            $model->recordRevision($changes);
        });

        static::updated(function (Model $model): void {
            $model->recordRevision($model->getRevisionableChanges());
        });
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(EntityRevision::class, 'revisionable')->latest();
    }

    /**
     * @return array<int, string>
     */
    public function getRevisionableAttributes(): array
    {
        return $this->revisionable ?? [];
    }

    /**
     * @return array<string, array{old: mixed, new: mixed}>
     */
    protected function getRevisionableChanges(): array
    {
        $changes = [];

        foreach ($this->getRevisionableAttributes() as $field) {
            if (! array_key_exists($field, $this->getChanges())) {
                continue;
            }

            $changes[$field] = [
                'old' => $this->getOriginal($field),
                'new' => $this->getAttribute($field),
            ];
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    protected function recordRevision(array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $causer = auth()->user();

        $this->revisions()->create([
            'causer_type' => $causer ? $causer::class : null,
            'causer_id' => $causer?->getKey(),
            'source' => EntityRevision::SOURCE_ADMIN,
            'changes' => $changes,
        ]);
    }
}
