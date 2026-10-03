<?php

namespace App\Modules\Content\Rules;

final class ContentStatusRules
{
    public const TYPES = ['youtube', 'song', 'movie', 'book', 'grammar'];

    public const STATUSES = ['draft', 'pending', 'processing', 'ready', 'rejected', 'failed'];

    public const PUBLIC_STATUSES = ['ready'];

    public const TRANSITIONS = [
        'draft' => ['pending', 'rejected'],
        'pending' => ['processing', 'failed', 'rejected'],
        'processing' => ['ready', 'failed'],
        'ready' => ['rejected'],
        'rejected' => ['pending'],
        'failed' => ['pending', 'rejected'],
    ];

    /** @return list<string> */
    public function allowedTransitions(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    public function isValidStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    public function canTransition(string $currentStatus, string $nextStatus): bool
    {
        return in_array($nextStatus, $this->allowedTransitions($currentStatus), true);
    }

    public function isPublic(string $status): bool
    {
        return in_array($status, self::PUBLIC_STATUSES, true);
    }
}
