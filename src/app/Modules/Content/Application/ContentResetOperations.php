<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\ContentProcessingOrchestratorInterface;
use App\Modules\Content\Application\Contracts\ContentResetOperationsInterface;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\LexemeExample;
use App\Modules\Content\Domain\Models\LexemeTranslation;
use App\Modules\Content\Application\Contracts\LearningProgressReferencesInterface;

final class ContentResetOperations implements ContentResetOperationsInterface
{
    public function __construct(
        private readonly LearningProgressReferencesInterface $learningProgress,
        private readonly ContentProcessingOrchestratorInterface $processing,
        private readonly AiAnalysisAutoDispatchService $aiDispatch,
    ) {}

    public function removeUnlearnedAiLexemes(int $contentId): array
    {
        $content = Content::query()->findOrFail($contentId);
        $candidateGrammarIds = $content->grammarCandidates()
            ->whereNotNull('matched_grammar_rule_id')
            ->pluck('matched_grammar_rule_id')
            ->all();

        $aiLexemes = $content->lexemes()->where('origin', ContentLexeme::ORIGIN_AI)->get();
        $protectedLexemeIds = $this->learningProgress->referencedLexemeIds(
            $aiLexemes->pluck('lexeme_id')->filter()->all()
        );
        $deleted = 0;
        $preserved = 0;

        foreach ($aiLexemes as $contentLexeme) {
            if ($this->learningProgress->hasContentLexeme($contentLexeme->id)) {
                $preserved++;

                continue;
            }

            if (! in_array($contentLexeme->lexeme_id, $protectedLexemeIds, true)) {
                LexemeExample::query()
                    ->where('content_id', $contentId)
                    ->where('lexeme_id', $contentLexeme->lexeme_id)
                    ->delete();
                LexemeTranslation::query()
                    ->where('content_id', $contentId)
                    ->where('lexeme_id', $contentLexeme->lexeme_id)
                    ->delete();
            }

            $contentLexeme->delete();
            $deleted++;
        }

        if ($candidateGrammarIds !== []) {
            $content->grammarRuleLinks()->whereIn('grammar_rule_id', $candidateGrammarIds)->delete();
        }

        return compact('deleted', 'preserved');
    }

    public function resetFullContent(int $contentId): int
    {
        $content = Content::query()->findOrFail($contentId);
        $deleted = $content->lexemes()->count();
        $content->lexemes()->delete();
        $content->grammarRuleLinks()->delete();
        $content->update([
            'status' => 'pending',
            'analysis_stale_at' => now(),
            'processing_failure_reason' => null,
        ]);

        return $deleted;
    }

    public function rerun(int $contentId, bool $reprocessContent): void
    {
        $content = Content::query()->findOrFail($contentId);

        if ($reprocessContent) {
            $this->processing->request($content);
        } else {
            $this->aiDispatch->dispatchFor($content);
        }
    }
}
