<?php

use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Application\Contracts\AcceptedCandidateWriterInterface;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentGrammarCandidate;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('VIK-16: applying an unmatched candidate whose title already exists links that rule instead of creating another', function () {
    // Two runs matched before either applied: the second one's candidate
    // carries no match although the first just created the rule.
    $topic = GrammarTopic::query()->create(['slug' => 't', 'language' => 'en', 'name' => 'T', 'status' => 'active']);
    $existing = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'passive-voice', 'language' => 'en', 'title' => 'Passive Voice', 'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
    GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'passive-voice-de', 'language' => 'de', 'title' => 'Passive Voice', 'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
    $content = Content::query()->create(['type' => 'youtube', 'title' => 'V', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready', 'source_text' => 't']);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_PENDING]);
    $candidate = $run->grammarCandidates()->create(['title' => 'passive voice', 'summary' => 'other words', 'status' => ContentGrammarCandidate::STATUS_ACCEPTED]);
    $created = [];

    app(AcceptedCandidateWriterInterface::class)->apply($run->id, $content->id, 'ru', function (int $id) use (&$created): void {
        $created[] = $id;
    });

    expect(GrammarRule::query()->where('language', 'en')->count())->toBe(1)
        ->and($created)->toBe([])
        ->and($content->grammarRules()->pluck('grammar_rules.id')->all())->toBe([$existing->id])
        ->and($candidate->refresh()->status)->toBe(ContentGrammarCandidate::STATUS_APPLIED)
        ->and($candidate->matched_grammar_rule_id)->toBe($existing->id);
});
