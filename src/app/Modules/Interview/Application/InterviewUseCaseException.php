<?php

namespace App\Modules\Interview\Application;

use RuntimeException;

final class InterviewUseCaseException extends RuntimeException
{
    public const NOT_FOUND = 'not_found';

    public const CONFLICT = 'conflict';

    public const INVALID = 'invalid';

    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message);
    }

    public static function ensure(bool $condition, string $reason, string $message = ''): void
    {
        if (! $condition) {
            throw new self($reason, $message);
        }
    }
}
