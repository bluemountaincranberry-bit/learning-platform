<?php

namespace App\Modules\Content\Application\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface ContentViewAuthorizationInterface
{
    public function assertCanView(Authenticatable $user, int $contentId): void;
}
