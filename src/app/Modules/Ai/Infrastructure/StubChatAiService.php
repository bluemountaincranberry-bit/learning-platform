<?php

namespace App\Modules\Ai\Infrastructure;

use App\Contracts\Ai\ChatAiServiceInterface;

class StubChatAiService implements ChatAiServiceInterface
{
    public function reply(array $history, string $newUserMessage): string
    {
        return 'Stub AI reply.';
    }
}
