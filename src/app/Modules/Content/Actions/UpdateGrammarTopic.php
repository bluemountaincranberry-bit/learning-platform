<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Application\Support\UniqueSlugResolver;
use App\Modules\Content\Domain\Models\GrammarTopic;

final class UpdateGrammarTopic
{
    public function __construct(private readonly UniqueSlugResolver $slugResolver) {}

    public function execute(GrammarTopic $topic, array $topicData): GrammarTopic
    {
        $topic->fill([
            'slug' => array_key_exists('slug', $topicData)
                ? $this->slugResolver->resolve(GrammarTopic::class, $topicData['slug'], $topicData['name'] ?? $topic->name, (int) $topic->id)
                : $topic->slug,
            'language' => $topicData['language'] ?? $topic->language,
            'name' => $topicData['name'] ?? $topic->name,
            'description' => $topicData['description'] ?? $topic->description,
            'status' => $topicData['status'] ?? $topic->status,
            'sort_order' => $topicData['sort_order'] ?? $topic->sort_order,
        ])->save();

        return $topic->fresh();
    }
}
