<?php

namespace App\Modules\Ai\Interfaces\Jobs;

use App\Contracts\Ai\EmbeddingsClientInterface;
use App\Modules\Ai\Domain\Models\CanonicalLexemeEmbedding;
use App\Modules\Content\Application\Contracts\EmbeddingSourceReaderInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ComputeCanonicalLexemeEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function backoff(): array
    {
        return [30, 60, 120];
    }

    /** @param array<int> $lexemeIds */
    public function __construct(private readonly array $lexemeIds) {}

    public function handle(EmbeddingsClientInterface $embeddings, EmbeddingSourceReaderInterface $sources): void
    {
        $modelVersion = config('ai.embeddings.model', 'text-embedding-3-small');

        $lexemes = $sources->canonicalLexemes($this->lexemeIds);

        foreach ($lexemes as $lexeme) {
            $vector = $embeddings->embed($lexeme['text']);

            CanonicalLexemeEmbedding::query()->updateOrCreate(
                ['lexeme_id' => $lexeme['id'], 'model_version' => $modelVersion],
                ['embedding' => $vector],
            );
        }
    }
}
