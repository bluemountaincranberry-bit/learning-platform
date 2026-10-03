<?php

namespace App\Policies;

use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\User\Models\User;

class SrsCardPolicy
{
    public function view(User $user, SrsCard $card): bool
    {
        return $user->id === $card->user_id;
    }

    public function update(User $user, SrsCard $card): bool
    {
        return $user->id === $card->user_id;
    }
}
