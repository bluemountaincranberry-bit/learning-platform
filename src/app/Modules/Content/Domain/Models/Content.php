<?php

namespace App\Modules\Content\Domain\Models;

use App\Modules\Content\Rules\ContentStatusRules;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Scout\Searchable;

/** Content aggregate root owned by the Content module. */
class Content extends Model
{
    use HasFactory, Searchable;

    protected static function newFactory(): \Database\Factories\ContentFactory
    {
        return \Database\Factories\ContentFactory::new();
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'moderated_at' => 'datetime',
            'processing_requested_at' => 'datetime',
            'processing_completed_at' => 'datetime',
            'transcript_accepted_at' => 'datetime',
            'analysis_stale_at' => 'datetime',
        ];
    }

    public function searchableAs(): string
    {
        return 'contents';
    }

    /** @return array<string, mixed> */
    public function toSearchableArray(): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'type' => $this->type, 'language' => $this->language, 'level' => $this->level, 'status' => $this->status];
    }

    public const TYPES = ContentStatusRules::TYPES;

    public const STATUSES = ContentStatusRules::STATUSES;

    public const PUBLIC_STATUSES = ContentStatusRules::PUBLIC_STATUSES;

    public const STATUS_TRANSITIONS = ContentStatusRules::TRANSITIONS;

    public const CEFR_LEVELS = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

    /** @return array<int, string> */
    public static function allowedTransitionsFor(string $status): array
    {
        return (new ContentStatusRules)->allowedTransitions($status);
    }

    public static function isValidStatus(string $status): bool
    {
        return (new ContentStatusRules)->isValidStatus($status);
    }

    public function canTransitionTo(string $nextStatus): bool
    {
        return (new ContentStatusRules)->canTransition((string) $this->status, $nextStatus);
    }

    public function isPubliclyVisible(): bool
    {
        return (new ContentStatusRules)->isPublic((string) $this->status);
    }

    public function hasTranscript(): bool
    {
        return trim((string) ($this->source_text ?? '')) !== '';
    }

    public function isTranscriptAccepted(): bool
    {
        return $this->transcript_accepted_at !== null && $this->analysis_stale_at === null;
    }

    public function hasTranscriptAcceptance(): bool
    {
        return $this->transcript_accepted_at !== null;
    }

    /** @return array<string, mixed> */
    public function transcriptChangedAttributes(string $newTranscript): array
    {
        if (trim((string) ($this->source_text ?? '')) === trim($newTranscript)) {
            return [];
        }

        return ['transcript_accepted_at' => null, 'transcript_accepted_by' => null, 'analysis_stale_at' => $this->hasDownstreamAnalysis() ? now() : null];
    }

    public function markTranscriptAccepted(?int $userId): void
    {
        $this->update(['transcript_accepted_at' => now(), 'transcript_accepted_by' => $userId]);
    }

    public function hasDownstreamAnalysis(): bool
    {
        return $this->status === 'ready' || $this->lexemes()->exists() || $this->grammarRuleLinks()->exists();
    }

    public function transcriptAccepter(): BelongsTo
    {
        return $this->belongsTo($this->userModelClass(), 'transcript_accepted_by');
    }

    public function lexemes(): HasMany
    {
        return $this->hasMany(ContentLexeme::class, 'content_id');
    }

    public function transcriptSegments(): HasMany
    {
        return $this->hasMany(TranscriptSegment::class)->orderBy('sequence');
    }

    public function analysisRuns(): HasMany
    {
        return $this->hasMany('App\\Modules\\Ai\\Domain\\Models\\AiAnalysisRun', 'content_id');
    }

    public function latestAnalysisRun(): HasOne
    {
        return $this->hasOne('App\\Modules\\Ai\\Domain\\Models\\AiAnalysisRun', 'content_id')->latestOfMany();
    }

    public function lexemeCandidates(): HasManyThrough
    {
        return $this->hasManyThrough(ContentLexemeCandidate::class, 'App\\Modules\\Ai\\Domain\\Models\\AiAnalysisRun', 'content_id', 'ai_analysis_run_id')->latest('content_lexeme_candidates.id');
    }

    public function grammarCandidates(): HasManyThrough
    {
        return $this->hasManyThrough(ContentGrammarCandidate::class, 'App\\Modules\\Ai\\Domain\\Models\\AiAnalysisRun', 'content_id', 'ai_analysis_run_id')->latest('content_grammar_candidates.id');
    }

    public function grammarRuleLinks(): HasMany
    {
        return $this->hasMany(ContentRuleLink::class, 'content_id');
    }

    public function grammarRules(): BelongsToMany
    {
        return $this->belongsToMany(GrammarRule::class, 'content_rule_links')->withPivot(['status', 'note'])->withTimestamps();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo($this->userModelClass(), 'created_by');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo($this->userModelClass(), 'moderator_id');
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    private function userModelClass(): string
    {
        return (string) config('auth.providers.users.model');
    }
}
