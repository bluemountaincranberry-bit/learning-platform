<?php

namespace App\Policies;

use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\User\Models\User;
use Illuminate\Auth\Access\Response;

final class LexemePolicy
{
    public function view(?User $user, Lexeme $lexeme): Response
    {
        return $lexeme->owner_user_id === null
            || ($user !== null && (int) $lexeme->owner_user_id === (int) $user->id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function manageCatalog(User $user, Lexeme $lexeme): Response
    {
        return $lexeme->owner_user_id === null
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
