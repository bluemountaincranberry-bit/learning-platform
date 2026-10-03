<?php

namespace App\Modules\Content\Application\Contracts;

use App\Modules\Content\Domain\Models\Content;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ContentRepositoryInterface
{
    /**
     * @param  array{type?: string, language?: string, level?: string}  $filters
     */
    public function getReadyPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?Content;

    public function getByUserSubmissions(int $userId, int $limit = 50): Collection;

    /**
     * @param  array{type: string, title: string, language: string, level?: string|null, origin: string, status: string, source_url: string, created_by: int}  $data
     */
    public function create(array $data): Content;
}
