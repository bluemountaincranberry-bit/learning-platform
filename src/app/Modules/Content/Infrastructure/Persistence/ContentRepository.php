<?php

namespace App\Modules\Content\Infrastructure\Persistence;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Application\Contracts\ContentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ContentRepository implements ContentRepositoryInterface
{
    /**
     * @param  array{type?: string, language?: string, level?: string, q?: string, created_by?: int}  $filters
     */
    public function getReadyPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $q = isset($filters['q']) ? trim($filters['q']) : '';
        $useScout = config('scout.driver') === 'elastic' && $q !== '';

        if ($useScout) {
            $builder = Content::search($q)->where('status', 'ready');
            if (! empty($filters['type'])) {
                $builder->where('type', $filters['type']);
            }
            if (! empty($filters['language'])) {
                $builder->where('language', $filters['language']);
            }
            if (! empty($filters['level'])) {
                $builder->where('level', $filters['level']);
            }
            if (! empty($filters['created_by'])) {
                $builder->where('created_by', $filters['created_by']);
            }

            return $builder->paginate($perPage);
        }

        $query = Content::query()->where('status', 'ready');

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['language'])) {
            $query->where('language', $filters['language']);
        }
        if (! empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }
        if (! empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }
        if ($q !== '') {
            $query->where('title', 'ilike', '%' . addcslashes($q, '%_\\') . '%');
        }

        return $query->latest()->paginate($perPage);
    }

    public function find(int $id): ?Content
    {
        return Content::query()->find($id);
    }

    public function getByUserSubmissions(int $userId, int $limit = 50): Collection
    {
        return Content::query()
            ->where('created_by', $userId)
            ->where('origin', 'user-submitted')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array{type: string, title: string, language: string, level?: string|null, origin: string, status: string, source_url: string, created_by: int}  $data
     */
    public function create(array $data): Content
    {
        return Content::create($data);
    }
}
