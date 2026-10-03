<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\ContentViewAuthorizationInterface;
use App\Modules\Content\Domain\Models\Content;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

final class ContentViewAuthorization implements ContentViewAuthorizationInterface
{
    public function assertCanView(Authenticatable $user, int $contentId): void
    {
        $content = Content::query()->findOrFail($contentId);

        abort_unless(Gate::forUser($user)->allows('view', $content), 404);
    }
}
