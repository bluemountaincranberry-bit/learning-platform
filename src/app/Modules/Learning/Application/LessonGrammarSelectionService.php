<?php

namespace App\Modules\Learning\Application;

use App\Modules\Content\Application\Contracts\GrammarProgressServiceInterface;
use App\Modules\Content\Application\Contracts\PersonalGrammarRuleWriterInterface;
use App\Modules\Content\Application\Data\PersonalGrammarRuleDraft;
use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\Learning\Domain\Models\LessonGrammarCandidate;
use Illuminate\Support\Facades\DB;

final class LessonGrammarSelectionService
{
    public function __construct(
        private readonly GrammarProgressServiceInterface $grammarProgress,
        private readonly PersonalGrammarRuleWriterInterface $personalRules,
    ) {}

    /** @return array{grammar_rule_id: int, is_personal: bool, in_my_grammar: bool, status: string} */
    public function addToMyGrammar(Lesson $lesson, int $candidateId, int $userId): array
    {
        return DB::transaction(function () use ($lesson, $candidateId, $userId): array {
            /** @var LessonGrammarCandidate $candidate */
            $candidate = $lesson->grammarCandidates()->lockForUpdate()->findOrFail($candidateId);
            $ruleId = $candidate->personal_grammar_rule_id !== null
                && $this->personalRules->findOwnedPersonalRule((int) $candidate->personal_grammar_rule_id, $userId)
                ? (int) $candidate->personal_grammar_rule_id
                : null;
            $isPersonal = $ruleId !== null;

            if ($ruleId === null && $candidate->matched_grammar_rule_id !== null) {
                $matchedId = (int) $candidate->matched_grammar_rule_id;
                if ($this->personalRules->isPublishedCatalogRule($matchedId, (string) $lesson->language)) {
                    $ruleId = $matchedId;
                }
            }

            if ($ruleId === null) {
                $ruleId = $this->personalRules->findPublishedMatch($candidate->title, $lesson->language);
            }

            if ($ruleId === null) {
                $ruleId = $this->personalRules->createFromLesson(new PersonalGrammarRuleDraft(
                    ownerUserId: $userId,
                    sourceLessonId: $lesson->id,
                    sourceLessonTitle: (string) ($lesson->title ?: 'Lesson'),
                    language: (string) $lesson->language,
                    title: (string) $candidate->title,
                    summary: $candidate->summary,
                    body: $candidate->body,
                    example: $candidate->example,
                    exampleTranslation: $candidate->example_translation,
                ));
                $isPersonal = true;
            }

            $candidate->forceFill([
                'matched_grammar_rule_id' => $isPersonal ? null : $ruleId,
                'personal_grammar_rule_id' => $isPersonal ? $ruleId : null,
                'status' => LessonGrammarCandidate::STATUS_LINKED,
            ])->save();

            $this->grammarProgress->startLearningById($ruleId, $userId);

            return [
                'grammar_rule_id' => $ruleId,
                'matched_grammar_rule_id' => $isPersonal ? null : $ruleId,
                'personal_grammar_rule_id' => $isPersonal ? $ruleId : null,
                'is_personal' => $isPersonal,
                'in_my_grammar' => true,
                'status' => LessonGrammarCandidate::STATUS_LINKED,
            ];
        });
    }
}
