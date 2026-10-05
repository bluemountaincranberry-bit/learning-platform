<?php

namespace App\Modules\Content\Application\Contracts;

interface PersonalLexemeReconcilerInterface
{
    public function reconcile(int $ownerUserId, int $privateLexemeId, int $sharedLexemeId): void;
}
