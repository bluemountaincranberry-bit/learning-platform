<?php

namespace App\Console\Commands;

use App\Modules\Ai\Interfaces\Jobs\ComputeEmbeddingsJob;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Ai\Domain\Models\LexemeEmbedding;
use Illuminate\Console\Command;

class ComputeEmbeddingsCommand extends Command
{
    protected $signature = 'ai:embed-lexemes
                            {--all : Process all lexemes missing embeddings for current model}
                            {--ids= : Comma-separated content_lexeme IDs}';

    protected $description = 'Dispatch job(s) to compute and store embeddings for lexemes (re-index or specific IDs)';

    public function handle(): int
    {
        $modelVersion = config('ai.embeddings.model', 'text-embedding-3-small');

        if ($this->option('all')) {
            $existingIds = LexemeEmbedding::query()
                ->where('model_version', $modelVersion)
                ->pluck('content_lexeme_id')
                ->all();

            $ids = ContentLexeme::query()
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
            ComputeEmbeddingsJob::dispatch($chunk);
        }

        $this->info(sprintf('Dispatched %d job(s) for %d lexeme(s).', count($chunks), count($ids)));

        return self::SUCCESS;
    }
}
