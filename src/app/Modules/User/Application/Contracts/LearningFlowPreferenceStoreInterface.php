<?php

namespace App\Modules\User\Application\Contracts;

interface LearningFlowPreferenceStoreInterface
{
    /** @return array<string, mixed>|null */
    public function forUser(int $userId): ?array;

    /**
     * @param  array<string, mixed>  $preferences
     * @return array<string, mixed>
     */
    public function updateForUser(int $userId, array $preferences): array;
}
