<?php

namespace App\Modules\Interview\Application;

use App\Modules\Interview\Domain\Models\InterviewAnswerRevision;
use App\Modules\Interview\Domain\Models\InterviewQuestion;
use App\Modules\Interview\Domain\Models\InterviewTag;
use App\Modules\Interview\Domain\Models\InterviewTopic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class InterviewQuestionBankService
{
    public function topics(int $userId): Collection
    {
        return InterviewTopic::query()->where('user_id', $userId)->orderBy('sort_order')->orderBy('name')->get();
    }

    public function tags(int $userId): Collection
    {
        return InterviewTag::query()->where('user_id', $userId)->orderBy('name')->pluck('name');
    }

    public function createTopic(int $userId, array $data): InterviewTopic
    {
        if (! empty($data['parent_id'])) {
            $this->ownedTopic((int) $data['parent_id'], $userId);
        }

        return InterviewTopic::query()->create([...$data, 'user_id' => $userId]);
    }

    public function updateTopic(int $userId, int $topicId, array $data): InterviewTopic
    {
        $topic = $this->ownedTopic($topicId, $userId);
        if (isset($data['parent_id'])) {
            $this->ownedTopic((int) $data['parent_id'], $userId);
            InterviewUseCaseException::ensure(! $this->topicSubtreeIds($topic->id, $userId)->contains((int) $data['parent_id']), InterviewUseCaseException::INVALID);
        }
        $topic->update($data);

        return $topic->fresh();
    }

    public function deleteTopic(int $userId, int $topicId): void
    {
        $this->ownedTopic($topicId, $userId)->delete();
    }

    /** @param array<string, mixed> $filters */
    public function questions(int $userId, array $filters): LengthAwarePaginator
    {
        $query = InterviewQuestion::query()->with(['topic:id,name,parent_id', 'tags:id,name', 'answers:id,question_id,kind,text_en,text_ru,revisions:id,answer_variant_id,text_en,text_ru,created_at'])
            ->where('user_id', $userId);
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($like, $search): void {
                if (DB::connection()->getDriverName() === 'pgsql') {
                    $q->whereRaw('prompt_en ILIKE ?', ['%'.$search.'%'])->orWhereRaw('COALESCE(prompt_ru, \'\') ILIKE ?', ['%'.$search.'%']);
                } else {
                    $q->whereRaw('LOWER(prompt_en) LIKE ?', [$like])->orWhere('prompt_en', 'like', '%'.$search.'%')
                        ->orWhere('prompt_ru', 'like', '%'.$search.'%');
                }
            });
        }
        if (! empty($filters['state'])) {
            $query->where('preparation_state', $filters['state']);
        }
        if (! empty($filters['topic_id'])) {
            $topic = $this->ownedTopic((int) $filters['topic_id'], $userId);
            $query->whereIn('topic_id', $this->topicSubtreeIds($topic->id, $userId));
        }
        if (! empty($filters['tag'])) {
            $query->whereHas('tags', fn ($q) => $q->where('name', $filters['tag'])->where('interview_tags.user_id', $userId));
        }

        return $query->orderBy('id')->paginate(min(100, max(1, (int) ($filters['per_page'] ?? 30))));
    }

    public function question(int $userId, int $questionId): InterviewQuestion
    {
        return $this->ownedQuestion($questionId, $userId);
    }

    public function createQuestion(int $userId, array $data): InterviewQuestion
    {
        if (! empty($data['topic_id'])) {
            $this->ownedTopic((int) $data['topic_id'], $userId);
        }

        return DB::transaction(function () use ($data, $userId): InterviewQuestion {
            $question = InterviewQuestion::query()->create([
                'user_id' => $userId, 'topic_id' => $data['topic_id'] ?? null,
                'prompt_en' => $data['prompt_en'], 'prompt_ru' => $data['prompt_ru'] ?? null,
                'preparation_state' => $data['preparation_state'] ?? 'unpracticed',
            ]);
            $this->syncTags($question, $data['tags'] ?? []);
            $this->saveAnswers($question, $data['answers'] ?? ['short' => [], 'full' => []], false, $userId);

            return $question->fresh(['topic', 'tags', 'answers.revisions']);
        });
    }

    public function updateQuestion(int $userId, int $questionId, array $data): InterviewQuestion
    {
        $question = $this->ownedQuestion($questionId, $userId);
        if (! empty($data['topic_id'])) {
            $this->ownedTopic((int) $data['topic_id'], $userId);
        }
        DB::transaction(function () use ($question, $data, $userId): void {
            $question->update(collect($data)->except(['tags', 'answers'])->all());
            if (array_key_exists('tags', $data)) {
                $this->syncTags($question, $data['tags']);
            }
            if (isset($data['answers'])) {
                $this->saveAnswers($question, $data['answers'], true, $userId);
            }
        });

        return $question->fresh(['topic', 'tags', 'answers.revisions']);
    }

    public function deleteQuestion(int $userId, int $questionId): void
    {
        $this->ownedQuestion($questionId, $userId)->delete();
    }

    public function restoreAnswerRevision(int $userId, int $questionId, int $answerId, int $revisionId): InterviewQuestion
    {
        $question = $this->ownedQuestion($questionId, $userId);
        $variant = $question->answers()->findOrFail($answerId);
        $snapshot = $variant->revisions()->findOrFail($revisionId);
        DB::transaction(function () use ($variant, $snapshot, $userId): void {
            InterviewAnswerRevision::query()->create([
                'answer_variant_id' => $variant->id, 'created_by' => $userId,
                'text_en' => $variant->text_en, 'text_ru' => $variant->text_ru,
            ]);
            $variant->update(['text_en' => $snapshot->text_en, 'text_ru' => $snapshot->text_ru]);
        });

        return $question->fresh(['topic', 'tags', 'answers.revisions']);
    }

    private function syncTags(InterviewQuestion $question, array $tags): void
    {
        $ids = collect($tags)->map(fn (string $name) => InterviewTag::query()->firstOrCreate(
            ['user_id' => $question->user_id, 'name' => trim($name)]
        )->id)->all();
        $question->tags()->sync($ids);
    }

    private function saveAnswers(InterviewQuestion $question, array $answers, bool $recordRevision, int $userId): void
    {
        foreach (['short', 'full'] as $kind) {
            if (! array_key_exists($kind, $answers)) {
                continue;
            }
            $variant = $question->answers()->firstOrNew(['kind' => $kind]);
            $next = ['text_en' => $answers[$kind]['en'] ?? null, 'text_ru' => $answers[$kind]['ru'] ?? null];
            if ($variant->exists && $recordRevision && ($variant->text_en !== $next['text_en'] || $variant->text_ru !== $next['text_ru'])) {
                InterviewAnswerRevision::query()->create([
                    'answer_variant_id' => $variant->id, 'created_by' => $userId,
                    'text_en' => $variant->text_en, 'text_ru' => $variant->text_ru,
                ]);
            }
            $variant->fill($next)->save();
        }
    }

    private function ownedTopic(int $topicId, int $userId): InterviewTopic
    {
        return InterviewTopic::query()->where('user_id', $userId)->findOrFail($topicId);
    }

    private function ownedQuestion(int $questionId, int $userId): InterviewQuestion
    {
        return InterviewQuestion::query()->with(['topic', 'tags', 'answers.revisions'])->where('user_id', $userId)->findOrFail($questionId);
    }

    /** @return Collection<int, int> */
    private function topicSubtreeIds(int $topicId, int $userId): Collection
    {
        $ids = collect([$topicId]);
        $frontier = $ids;
        while ($frontier->isNotEmpty()) {
            $frontier = InterviewTopic::query()->where('user_id', $userId)->whereIn('parent_id', $frontier)->pluck('id');
            $ids = $ids->concat($frontier);
        }

        return $ids;
    }
}
