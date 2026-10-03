<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Domain\Models\GrammarTopic;

final class DeleteGrammarTopic
{
    public function execute(GrammarTopic $topic): void
    {
        $topic->delete();
    }
}
