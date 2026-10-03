<?php

namespace App\Modules\Content\Application\Contracts;

use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface GrammarCatalogServiceInterface
{
    public function paginateTopics(array $filters): LengthAwarePaginator;

    public function createTopic(array $data): GrammarTopic;

    public function updateTopic(GrammarTopic $topic, array $data): GrammarTopic;

    public function deleteTopic(GrammarTopic $topic): void;

    public function paginateRules(array $filters): LengthAwarePaginator;

    public function createRule(array $data): GrammarRule;

    public function getRule(GrammarRule $rule): GrammarRule;

    public function updateRule(GrammarRule $rule, array $data): GrammarRule;

    public function deleteRule(GrammarRule $rule): void;

    public function paginateLexemes(array $filters): LengthAwarePaginator;

    public function createLexeme(array $data): Lexeme;

    public function getLexeme(Lexeme $lexeme): Lexeme;

    public function updateLexeme(Lexeme $lexeme, array $data): Lexeme;

    public function deleteLexeme(Lexeme $lexeme): void;

    /**
     * @return array<string, array<string, int|string>>
     */
    public function getCoverageSummary(array $filters = []): array;
}
