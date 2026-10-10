<?php

namespace App\Modules\Interview\Application;

use App\Modules\Interview\Application\Contracts\InterviewSessionContextReader;
use App\Modules\Interview\Domain\Models\InterviewPracticeSession;
use App\Modules\Interview\Domain\Models\InterviewProfile;
use App\Modules\Interview\Domain\Models\InterviewQuestion;
use App\Modules\Learning\Application\Contracts\StudentVocabularyReaderInterface;

final class InterviewSessionContext implements InterviewSessionContextReader
{
    public function __construct(
        private readonly InterviewPracticeService $practice,
        private readonly StudentVocabularyReaderInterface $vocabulary,
    ) {}

    public function forConversation(int $conversationId, int $userId): array
    {
        $session = InterviewPracticeSession::query()->where('user_id', $userId)
            ->where('agent_conversation_id', $conversationId)->firstOrFail();
        $profile = InterviewProfile::query()->where('user_id', $userId)->with(['milestones', 'observations'])->first();
        $questions = $this->practice->questionsForSession($session)->map(fn (InterviewQuestion $question) => [
            'id' => $question->id,
            'prompt_en' => $question->prompt_en,
            'prompt_ru' => $question->prompt_ru,
            'preparation_state' => $question->preparation_state,
            'topic' => $question->topic?->name,
            'tags' => $question->tags->pluck('name')->all(),
            'answers' => $question->answers->mapWithKeys(fn ($answer) => [$answer->kind => ['en' => $answer->text_en, 'ru' => $answer->text_ru]])->all(),
        ])->all();

        return [
            'mode' => $session->mode,
            'status' => $session->status,
            'focus' => $session->focus,
            'question_count' => $session->question_count,
            'profile' => $profile?->only(['career_goal', 'skills', 'experience_level', 'projects', 'experience_stories']),
            'milestones' => $profile?->milestones->map(fn ($milestone) => $milestone->only(['title', 'target_date']))->all() ?? [],
            'confirmed_observations' => $profile?->observations->map(fn ($observation) => [
                'type' => $observation->pattern_type,
                'summary' => $observation->summary,
                'examples' => $observation->examples,
            ])->all() ?? [],
            'questions' => $questions,
            'recent_completed_practice_evidence' => $this->recentCompletedEvidence($userId, $session->id),
            'learned_english_vocabulary' => $this->vocabulary->history($userId, 'en', 20),
        ];
    }

    /** @return array<int, array{session_id: int, question_id: int, question_prompt_en: string, evidence: string, source_message_id: int}> */
    private function recentCompletedEvidence(int $userId, int $currentSessionId): array
    {
        $sessions = InterviewPracticeSession::query()->where('user_id', $userId)
            ->where('status', 'completed')->where('id', '!=', $currentSessionId)
            ->latest('updated_at')->limit(10)->get();
        $evidence = [];
        foreach ($sessions as $priorSession) {
            $questionIds = array_map('intval', $priorSession->question_ids ?? []);
            if ($questionIds === []) {
                continue;
            }
            $questions = InterviewQuestion::query()->where('user_id', $userId)->whereIn('id', $questionIds)->get(['id', 'prompt_en']);
            $answers = [];
            $activeQuestionId = null;
            foreach ($this->practice->history($priorSession) as $message) {
                if ($message['role'] === 'assistant') {
                    $matchingIds = $questions->filter(fn (InterviewQuestion $question): bool => str_contains($message['content'], $question->prompt_en))
                        ->pluck('id')->all();
                    $activeQuestionId = count($matchingIds) === 1 ? (int) $matchingIds[0] : null;
                } else {
                    if ($activeQuestionId !== null) {
                        $answers[$activeQuestionId] = [
                            'session_id' => $priorSession->id,
                            'question_id' => $activeQuestionId,
                            'question_prompt_en' => $questions->firstWhere('id', $activeQuestionId)->prompt_en,
                            'evidence' => mb_substr($message['content'], 0, 1200),
                            'source_message_id' => $message['id'],
                        ];
                    }
                    $activeQuestionId = null;
                }
            }
            foreach ($answers as $answer) {
                $evidence[] = $answer;
                if (count($evidence) >= 8) {
                    return $evidence;
                }
            }
        }

        return $evidence;
    }
}
