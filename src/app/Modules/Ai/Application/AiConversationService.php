<?php

declare(strict_types=1);

namespace App\Modules\Ai\Application;

use App\Modules\Ai\Domain\Models\AiConversation;

class AiConversationService
{
    public function createForUser(int $userId): AiConversation
    {
        return AiConversation::create([
            'user_id' => $userId,
            'title' => null,
        ]);
    }
}
