<?php

namespace App\Modules\Content\Application\Contracts;

use Illuminate\Support\Collection;

interface RecommendationCatalogInterface
{
    public function contentCandidates(int $userId, ?string $language, int $limit): Collection;

    public function lexemeCandidates(int $userId, ?string $language, int $limit): Collection;
}
