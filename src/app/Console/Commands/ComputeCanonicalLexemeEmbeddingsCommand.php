<?php

namespace App\Console\Commands;

use App\Modules\Ai\Interfaces\Jobs\ComputeCanonicalLexemeEmbeddingsJob;
use App\Modules\Ai\Domain\Models\CanonicalLexemeEmbedding;
use App\Modules\Content\Domain\Models\Lexeme;
use Illuminate\Console\Command;

class ComputeCanonicalLexemeEmbeddingsCommand extends Command
{
    protected $signature = 'ai:embed-canonical-lexemes
                            {--all : Process all canonical lexemes missing embeddings for current model}
                            {--ids= : Comma-separated lexeme IDs}';

    protected $description = 'Dispatch job(s) to compute and store embeddings for canonical lexemes (used for AI candidate matching)';

    public function handle(): int
    {
        $modelVersion = config('ai.embeddings.model', 'text-embedding-3-small');

        if ($this->option('all')) {
            $existingIds = CanonicalLexemeEmbedding::query()
                ->where('model_version', $modelVersion)
                ->pluck('lexeme_id')
                ->all();

            $ids = Lexeme::query()
                ->whereNotIn('id', $existingIds)
                ->pluck('id')
                ->all();
        } elseif ($idsOption = $this->option('ids')) {
            $ids = array_map('intval', array_filter(explode(',', $idsOption)));
        } else {
            $this->error('Use --all or --ids=1,2,3');

            return self::FAILURE;
        }

        if (empty($ids)) {
            $this->info('No lexemes to process.');

            return self::SUCCESS;
        }

        $chunkSize = 50;
        $chunks = array_chunk($ids, $chunkSize);
        foreach ($chunks as $chunk) {
            ComputeCanonicalLexemeEmbeddingsJob::dispatch($chunk);
        }

        $this->info(sprintf('Dispatched %d job(s) for %d lexeme(s).', count($chunks), count($ids)));

        return self::SUCCESS;
    }
}
