<?php

namespace App\Modules\Ai\Interfaces\Jobs;

use App\Contracts\Ai\EmbeddingsClientInterface;
use App\Modules\Ai\Domain\Models\GrammarRuleEmbedding;
use App\Modules\Content\Application\Contracts\EmbeddingSourceReaderInterface;
use App\Support\AiConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ComputeGrammarRuleEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    /** @param array<int> $grammarRuleIds */
    public function __construct(private readonly array $grammarRuleIds) {}

    public function handle(EmbeddingsClientInterface $embeddings, EmbeddingSourceReaderInterface $sources): void
    {
        if (! AiConfig::isEnabled()) {
            return;
        }

        $modelVersion = config('ai.embeddings.model', 'text-embedding-3-small');
        $rules = $sources->grammarRules($this->grammarRuleIds);

        foreach ($rules as $rule) {
            $vector = $embeddings->embed($rule['text']);

            GrammarRuleEmbedding::query()->updateOrCreate(
                ['grammar_rule_id' => $rule['id'], 'model_version' => $modelVersion],
                ['embedding' => $vector],
            );
        }
    }
}
