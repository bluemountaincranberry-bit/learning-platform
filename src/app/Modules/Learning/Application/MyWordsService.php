<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\MyWordsCatalogInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class MyWordsService
{
    public function __construct(private readonly MyWordsCatalogInterface $words) {}

    /** @param array<string, mixed> $filters */
    public function getPaginated(int $userId, array $filters = []): LengthAwarePaginator
    {
        return $this->words->getPaginated($userId, $filters);
    }
}
