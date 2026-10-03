<?php

namespace App\Modules\Content\Application\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class UniqueSlugResolver
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function resolve(string $modelClass, ?string $requestedSlug, string $fallback, ?int $ignoredModelId = null): string
    {
        $baseSlug = Str::slug($requestedSlug !== null && $requestedSlug !== '' ? $requestedSlug : $fallback);
        $baseSlug = $baseSlug !== '' ? $baseSlug : Str::lower(Str::random(8));
        $slug = $baseSlug;
        $suffix = 2;

        while ($this->exists($modelClass, $slug, $ignoredModelId)) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function exists(string $modelClass, string $slug, ?int $ignoredModelId): bool
    {
        $query = $modelClass::query()->where('slug', $slug);

        if ($ignoredModelId !== null) {
            $query->whereKeyNot($ignoredModelId);
        }

        return $query->exists();
    }
}
