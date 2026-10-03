<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Application\Support\UniqueSlugResolver;
use App\Modules\Content\Domain\Models\GrammarTopic;

final class CreateGrammarTopic
{
    public function __construct(private readonly UniqueSlugResolver $slugResolver) {}

    public function execute(array $topicData): GrammarTopic
    {
        return GrammarTopic::query()->create([
            'slug' => $this->slugResolver->resolve(GrammarTopic::class, $topicData['slug'] ?? null, $topicData['name']),
            'language' => $topicData['language'] ?? 'en',
            'name' => $topicData['name'],
            'description' => $topicData['description'] ?? null,
            'status' => $topicData['status'] ?? GrammarTopic::STATUS_DRAFT,
            'sort_order' => $topicData['sort_order'] ?? 0,
        ]);
    }
}
