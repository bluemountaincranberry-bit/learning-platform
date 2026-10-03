<?php

namespace App\Modules\Content\Application\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MyWordsCatalogInterface
{
    /** @param array<string, mixed> $filters */
    public function getPaginated(int $userId, array $filters = []): LengthAwarePaginator;
}
