<?php

namespace App\Modules\Content\Interfaces\Jobs;

use App\Exceptions\AiClientException;
use App\Contracts\Ai\AiErrorMessage;
use App\Contracts\Ai\LexemeMetadataSuggestionCapability;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Support\AiConfig;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SuggestLexemeLevelJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $lexemeId) {}

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function tags(): array
    {
        return ['lexeme:'.$this->lexemeId, 'job:suggest-lexeme-level'];
    }

    public function uniqueId(): string
    {
        return 'SuggestLexemeLevel:'.$this->lexemeId;
    }

    public function handle(LexemeMetadataSuggestionCapability $service): void
    {
        if (! AiConfig::isEnabled() || ! config('ai.lexeme_metadata_suggestion.enabled', false)) {
            return;
        }

        $lexeme = DB::transaction(function () {
            $lexeme = Lexeme::query()->lockForUpdate()->find($this->lexemeId);
            if ($lexeme === null || ($lexeme->level !== null && $lexeme->part_of_speech !== null)) {
                return null;
            }

            return $lexeme;
        });

        if ($lexeme === null) {
            return;
        }

        try {
            $metadata = $service->suggest($lexeme->lemma);
        } catch (AiClientException $e) {
            Log::warning('SuggestLexemeLevelJob: AI client failed', ['lexeme_id' => $this->lexemeId, 'message' => AiErrorMessage::safe($e)]);

            return;
        }

        $metadata = $metadata->toArray();
        $level = $metadata['level'];
        if ($level !== null && in_array($level, Content::CEFR_LEVELS, true)) {
            Lexeme::query()->whereKey($this->lexemeId)->whereNull('level')->update(['level' => $level]);
        } elseif ($level !== null) {
            Log::warning('SuggestLexemeLevelJob: AI returned an unrecognized level', ['lexeme_id' => $this->lexemeId, 'level' => $level]);
        }

        $partOfSpeech = $metadata['part_of_speech'];
        if ($partOfSpeech !== null && array_key_exists($partOfSpeech, Lexeme::PARTS_OF_SPEECH)) {
            Lexeme::query()->whereKey($this->lexemeId)->whereNull('part_of_speech')->update(['part_of_speech' => $partOfSpeech]);
        } elseif ($partOfSpeech !== null) {
            Log::warning('SuggestLexemeLevelJob: AI returned an unrecognized part of speech', ['lexeme_id' => $this->lexemeId, 'part_of_speech' => $partOfSpeech]);
        }
    }
}
