<?php

namespace App\Contracts\Ai;

final class AiErrorMessage
{
    public static function safe(\Throwable $exception): string
    {
        $message = preg_replace('/(sk-[A-Za-z0-9_-]{12,}|api[_-]?key\s*[=:]\s*[^\s,;]+)/i', '[redacted]', $exception->getMessage()) ?? 'AI operation failed.';

        return mb_substr($message, 0, 1000);
    }
}
