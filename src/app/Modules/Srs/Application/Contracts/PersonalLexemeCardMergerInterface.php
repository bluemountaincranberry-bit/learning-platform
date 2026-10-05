<?php

namespace App\Modules\Srs\Application\Contracts;

interface PersonalLexemeCardMergerInterface
{
    public function merge(int $userId, int $fromLexemeId, int $toLexemeId): void;
}
