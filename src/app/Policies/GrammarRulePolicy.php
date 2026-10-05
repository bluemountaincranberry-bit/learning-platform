<?php

namespace App\Policies;

use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\User\Models\User;
use Illuminate\Auth\Access\Response;

class GrammarRulePolicy
{
    public function view(?User $user, GrammarRule $rule): Response
    {
        if ($rule->status === GrammarRule::STATUS_PUBLISHED) {
            return Response::allow();
        }

        if (
            $rule->status === GrammarRule::STATUS_PERSONAL
            && $user !== null
            && (int) $rule->owner_user_id === (int) $user->id
        ) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }
}
