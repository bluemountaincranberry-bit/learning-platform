<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentRuleLink;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;

function makeContentForGrammarLinks(): Content
{
    return Content::query()->create([
        'type' => 'youtube', 'title' => 'C', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
}

function makeLinkableGrammarRule(string $status = GrammarRule::STATUS_PUBLISHED, string $title = 'Rule'): GrammarRule
{
    static $counter = 0;
    $counter++;

    $topic = GrammarTopic::query()->create(['slug' => "topic-{$counter}", 'language' => 'en', 'name' => "Topic {$counter}", 'status' => 'active']);

    return GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => "rule-{$counter}", 'language' => 'en', 'title' => $title, 'status' => $status,
    ]);
}

test('returns rules linked to the content via content_rule_links', function () {
    $content = makeContentForGrammarLinks();
    $linked = makeLinkableGrammarRule(title: 'Linked Rule');
    $unlinked = makeLinkableGrammarRule(title: 'Unlinked Rule');
    ContentRuleLink::query()->create(['content_id' => $content->id, 'grammar_rule_id' => $linked->id, 'status' => 'linked']);

    $response = $this->getJson("/api/content/{$content->id}/grammar-rules")->assertOk();

    $titles = collect($response->json('rules'))->pluck('title')->all();
    expect($titles)->toBe(['Linked Rule']);
});

test('excludes linked rules that are not published', function () {
    $content = makeContentForGrammarLinks();
    $draft = makeLinkableGrammarRule(GrammarRule::STATUS_DRAFT, 'Draft Linked Rule');
    ContentRuleLink::query()->create(['content_id' => $content->id, 'grammar_rule_id' => $draft->id, 'status' => 'linked']);

    $response = $this->getJson("/api/content/{$content->id}/grammar-rules")->assertOk();

    expect($response->json('rules'))->toBe([]);
});

test('returns an empty array, not an error, for a content with no linked rules', function () {
    $content = makeContentForGrammarLinks();

    $response = $this->getJson("/api/content/{$content->id}/grammar-rules")->assertOk();

    expect($response->json('rules'))->toBe([]);
});
