<?php

namespace App\Console\Commands;

use App\Modules\Ai\Interfaces\Jobs\ComputeGrammarRuleEmbeddingsJob;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Ai\Domain\Models\GrammarRuleEmbedding;
use Illuminate\Console\Command;

class ComputeGrammarRuleEmbeddingsCommand extends Command
{
    protected $signature = 'ai:embed-grammar-rules
                            {--all : Process all grammar rules missing embeddings for current model}
                            {--ids= : Comma-separated grammar rule IDs}';

    protected $description = 'Dispatch job(s) to compute and store embeddings for grammar rules (used for AI candidate matching)';

    public function handle(): int
    {
        $modelVersion = config('ai.embeddings.model', 'text-embedding-3-small');

        if ($this->option('all')) {
            $existingIds = GrammarRuleEmbedding::query()
                ->where('model_version', $modelVersion)
                ->pluck('grammar_rule_id')
                ->all();

            $ids = GrammarRule::query()
                ->whereNull('owner_user_id')
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
            $this->info('No grammar rules to process.');

            return self::SUCCESS;
        }

        $chunkSize = 50;
        $chunks = array_chunk($ids, $chunkSize);
        foreach ($chunks as $chunk) {
            ComputeGrammarRuleEmbeddingsJob::dispatch($chunk);
        }

        $this->info(sprintf('Dispatched %d job(s) for %d grammar rule(s).', count($chunks), count($ids)));

        return self::SUCCESS;
    }
}
