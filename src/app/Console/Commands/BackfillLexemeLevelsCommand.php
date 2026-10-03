<?php

namespace App\Console\Commands;

use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Interfaces\Jobs\SuggestLexemeLevelJob;
use Illuminate\Console\Command;

class BackfillLexemeLevelsCommand extends Command
{
    protected $signature = 'ai:backfill-lexeme-levels
                            {--all : Process all lexemes currently missing a level}
                            {--ids= : Comma-separated lexeme IDs}';

    protected $description = 'Dispatch SuggestLexemeLevelJob for existing lexemes that have no CEFR level yet (never touches lexemes that already have one)';

    public function handle(): int
    {
        if ($this->option('all')) {
            $ids = Lexeme::query()->whereNull('level')->pluck('id')->all();
        } elseif ($idsOption = $this->option('ids')) {
            $requestedIds = array_map('intval', array_filter(explode(',', $idsOption)));
            // Filter down to lexemes that still have no level — dispatching
            // for an already-leveled lexeme would just be a wasted job (the
            // job itself also re-checks this before calling the LLM, but
            // skipping here avoids the noise).
            $ids = Lexeme::query()->whereIn('id', $requestedIds)->whereNull('level')->pluck('id')->all();
        } else {
            $this->error('Use --all or --ids=1,2,3');

            return self::FAILURE;
        }

        if (empty($ids)) {
            $this->info('No lexemes to process.');

            return self::SUCCESS;
        }

        foreach ($ids as $id) {
            SuggestLexemeLevelJob::dispatch($id);
        }

        $this->info(sprintf('Dispatched %d job(s).', count($ids)));

        return self::SUCCESS;
    }
}
