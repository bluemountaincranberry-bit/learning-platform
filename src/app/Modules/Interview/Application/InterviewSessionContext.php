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
        $profile = InterviewProfile::query()->where('user_id', $userId)->with('milestones')->first();
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
            'questions' => $questions,
            'learned_english_vocabulary' => $this->vocabulary->history($userId, 'en', 20),
        ];
    }
}
