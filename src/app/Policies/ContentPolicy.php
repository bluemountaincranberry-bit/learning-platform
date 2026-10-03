<?php

namespace App\Policies;

use App\Modules\User\Models\User;
use App\Modules\Content\Domain\Models\Content;

class ContentPolicy
{
    /**
     * Allow viewing content only when it is ready (public visibility).
     */
    public function view(?User $user, Content $content): bool
    {
        return $content->isPubliclyVisible();
    }

    /**
     * EPIC 9: review/accept pending AI candidates for this content. This is
     * the "human in the loop" for AiCandidateApplyService moved out of
     * Filament admin-only — the content's own submitter is the allowed
     * reviewer, not Filament staff (see ai-platform-implementation-roadmap.md
     * section 10). `created_by` is nullable (curated/admin-authored content
     * has no submitter), so a null owner never matches any authenticated user.
     */
    public function reviewAiSuggestions(User $user, Content $content): bool
    {
        return $content->created_by !== null && $user->id === $content->created_by;
    }
}
