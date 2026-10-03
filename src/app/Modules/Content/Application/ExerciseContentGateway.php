<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\ExerciseContentGatewayInterface;
use App\Modules\Content\Application\Data\ExerciseContentContext;
use App\Modules\Content\Domain\Models\Content;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

final class ExerciseContentGateway implements ExerciseContentGatewayInterface
{
    public function resolveForCreation(
        Authenticatable $learner,
        int $contentId,
        ?int $contentLexemeId,
        ?int $transcriptSegmentId,
    ): ExerciseContentContext {
        $content = Content::query()->findOrFail($contentId);
        abort_unless(Gate::forUser($learner)->allows('view', $content), 404);

        return $this->buildContext($content, $contentLexemeId, $transcriptSegmentId, true);
    }

    public function contextForAttempt(
        int $contentId,
        ?int $contentLexemeId,
        ?int $transcriptSegmentId,
    ): ExerciseContentContext {
        $content = Content::query()->findOrFail($contentId);

        return $this->buildContext($content, $contentLexemeId, $transcriptSegmentId, false);
    }

    private function buildContext(
        Content $content,
        ?int $contentLexemeId,
        ?int $transcriptSegmentId,
        bool $validateReferences,
    ): ExerciseContentContext {
        $lexeme = $contentLexemeId === null ? null : $content->lexemes()->find($contentLexemeId);
        $segment = $transcriptSegmentId === null ? null : $content->transcriptSegments()->find($transcriptSegmentId);

        if ($validateReferences && $contentLexemeId !== null && $lexeme === null) {
            abort(422, 'The content lexeme does not belong to this content.');
        }
        if ($validateReferences && $transcriptSegmentId !== null && $segment === null) {
            abort(422, 'The transcript segment does not belong to this content.');
        }

        return new ExerciseContentContext(
            contentId: (int) $content->id,
            language: (string) $content->language,
            level: $content->level !== null ? (string) $content->level : null,
            contentLexemeId: $lexeme !== null ? (int) $lexeme->id : null,
            lexemeType: $lexeme?->type,
            lexemeText: $lexeme?->text,
            transcriptSegmentId: $segment !== null ? (int) $segment->id : null,
        );
    }
}
