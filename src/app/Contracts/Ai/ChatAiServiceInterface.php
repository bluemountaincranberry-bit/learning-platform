<?php

namespace App\Contracts\Ai;

interface ChatAiServiceInterface
{
    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function reply(array $history, string $newUserMessage): string;
}
