<?php

namespace App\Console\Commands;

use App\Modules\Ai\Application\RagIndexingService;
use Illuminate\Console\Command;

/**
 * Task 2.3: same shape as ComputeCanonicalLexemeEmbeddingsCommand/
 * ComputeGrammarRuleEmbeddingsCommand (--all / --ids), except this writes
 * to Elasticsearch instead of a Postgres embeddings table — see
 * RagIndexingService for why this runs synchronously instead of dispatching
 * a queued job per chunk.
 */
class IndexRagCorpusCommand extends Command
{
    protected $signature = 'rag:index-corpus
                            {--all : Index every published grammar rule and every example of a published lexeme}
                            {--type= : Limit to one corpus type: grammar_rule or lexeme_example (required when using --ids)}
                            {--ids= : Comma-separated source ids to (re)index, scoped by --type}';

    protected $description = 'Compute embeddings for the RAG corpus (grammar rule bodies + lexeme examples) and index them into Elasticsearch';

    public function handle(RagIndexingService $indexing): int
    {
        if (! $indexing->enabled()) {
            $this->warn('ELASTICSEARCH_ENABLED is false (see config/elasticsearch.php) — nothing to do.');

            return self::SUCCESS;
        }

        $type = $this->option('type');
        if ($type !== null && ! in_array($type, ['grammar_rule', 'lexeme_example'], true)) {
            $this->error('--type must be "grammar_rule" or "lexeme_example".');

            return self::FAILURE;
        }

        $idsOption = $this->option('ids');
        $ids = $idsOption ? array_map('intval', array_filter(explode(',', $idsOption))) : null;

        if ($ids !== null && $type === null) {
            $this->error('--ids requires --type=grammar_rule|lexeme_example.');

            return self::FAILURE;
        }

        if (! $this->option('all') && $ids === null) {
            $this->error('Use --all or --type=... --ids=1,2,3');

            return self::FAILURE;
        }

        $total = 0;

        if ($type === null || $type === 'grammar_rule') {
            $count = $indexing->indexGrammarRules($type === 'grammar_rule' ? $ids : null);
            $this->info("Indexed {$count} grammar rule(s).");
            $total += $count;
        }

        if ($type === null || $type === 'lexeme_example') {
            $count = $indexing->indexLexemeExamples($type === 'lexeme_example' ? $ids : null);
            $this->info("Indexed {$count} lexeme example(s).");
            $total += $count;
        }

        $this->info("Done. {$total} document(s) indexed into Elasticsearch.");

        return self::SUCCESS;
    }
}
