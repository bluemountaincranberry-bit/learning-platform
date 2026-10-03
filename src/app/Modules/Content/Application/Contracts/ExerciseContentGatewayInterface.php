<?php

namespace App\Modules\Content\Application\Contracts;

use App\Modules\Content\Application\Data\ExerciseContentContext;
use Illuminate\Contracts\Auth\Authenticatable;

interface ExerciseContentGatewayInterface
{
    public function resolveForCreation(
        Authenticatable $learner,
        int $contentId,
        ?int $contentLexemeId,
        ?int $transcriptSegmentId,
    ): ExerciseContentContext;

    public function contextForAttempt(
        int $contentId,
        ?int $contentLexemeId,
        ?int $transcriptSegmentId,
    ): ExerciseContentContext;
}
