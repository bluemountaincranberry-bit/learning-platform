<?php

use App\Modules\Ai\Domain\Models\GrammarRuleEmbedding;
use App\Modules\Content\Application\CatalogDuplicates\ForeignKeyGraph;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentRuleLink;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExample;
use App\Modules\Content\Domain\Models\GrammarRuleExampleHide;
use App\Modules\Content\Domain\Models\GrammarRuleExercise;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Learning\Domain\Models\UserGrammarRule;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function mergeRule(string $title, array $attributes = []): GrammarRule
{
    $topic = GrammarTopic::query()->firstOrCreate(['slug' => 'merge-topic'], ['language' => 'en', 'name' => 'Topic', 'status' => 'active']);

    return GrammarRule::query()->create($attributes + [
        'topic_id' => $topic->id, 'slug' => str()->slug($title).'-'.str()->random(5), 'language' => 'en',
        'title' => $title, 'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
}

function mergeVideo(string $url, string $title = 'Adele - Someone Like You'): Content
{
    return Content::query()->create([
        'type' => 'youtube', 'title' => $title, 'language' => 'en', 'origin' => 'user-submitted', 'status' => 'ready', 'source_url' => $url,
    ]);
}

/**
 * The dev catalog incident: "First Conditional" twice, the duplicate
 * linked to a content, carrying examples, an exercise, an embedding, and
 * the learner's progress on both copies; Adele submitted under two URLs.
 */
function seedIncident(): array
{
    $user = User::factory()->create();
    $keep = mergeRule('First Conditional', ['summary' => null]);
    $duplicate = mergeRule('First conditional', ['summary' => 'Real future possibility', 'level' => 'B1']);
    $contentA = mergeVideo('https://www.youtube.com/watch?v=hLQl3WQQoQ0&list=RD', 'A');
    $contentB = mergeVideo('https://youtu.be/FWTNMzK9vG4', 'B');

    ContentRuleLink::query()->create(['content_id' => $contentA->id, 'grammar_rule_id' => $keep->id, 'status' => 'linked']);
    ContentRuleLink::query()->create(['content_id' => $contentA->id, 'grammar_rule_id' => $duplicate->id, 'status' => 'linked']);
    ContentRuleLink::query()->create(['content_id' => $contentB->id, 'grammar_rule_id' => $duplicate->id, 'status' => 'linked']);

    $keptTwin = GrammarRuleExample::query()->create(['grammar_rule_id' => $keep->id, 'example' => 'If it rains, we will stay.', 'is_primary' => true]);
    $dupTwin = GrammarRuleExample::query()->create(['grammar_rule_id' => $duplicate->id, 'example' => 'If it rains we will stay', 'is_primary' => true]);
    $dupOwn = GrammarRuleExample::query()->create(['grammar_rule_id' => $duplicate->id, 'example' => 'If you call, I will answer.', 'is_primary' => false]);
    GrammarRuleExampleHide::query()->create(['user_id' => $user->id, 'grammar_rule_example_id' => $dupTwin->id]);

    $exercise = GrammarRuleExercise::query()->create(['grammar_rule_id' => $duplicate->id, 'type' => 'choice', 'prompt' => 'p']);
    GrammarRuleEmbedding::query()->create(['grammar_rule_id' => $duplicate->id, 'embedding' => [1.0, 0.0], 'model_version' => 'text-embedding-3-small']);

    UserGrammarRule::query()->create(['user_id' => $user->id, 'grammar_rule_id' => $keep->id, 'status' => 'learning', 'started_at' => '2026-08-20 10:00:00']);
    UserGrammarRule::query()->create(['user_id' => $user->id, 'grammar_rule_id' => $duplicate->id, 'status' => 'learned', 'started_at' => '2026-08-10 10:00:00', 'learned_at' => '2026-08-25 10:00:00', 'confidence_manual' => 0.8]);

    $adeleOld = mergeVideo('https://www.youtube.com/watch?v=hLQl3WQQoQ1&list=RDx');
    $adeleNew = mergeVideo('https://youtu.be/hLQl3WQQoQ1?si=DsaRPbipwo4dATTd');
    $adeleNew->transcriptSegments()->create(['sequence' => 0, 'start_ms' => 0, 'text' => 'Never mind']);

    return compact('user', 'keep', 'duplicate', 'contentA', 'contentB', 'keptTwin', 'dupTwin', 'dupOwn', 'exercise', 'adeleOld', 'adeleNew');
}

test('dry run reports the plan and writes nothing', function () {
    $s = seedIncident();
    $before = [GrammarRule::query()->pluck('status', 'id')->all(), ContentRuleLink::query()->count(), UserGrammarRule::query()->count(), Content::query()->pluck('status', 'id')->all()];

    $this->artisan('catalog:merge-duplicates')
        ->expectsOutputToContain('Dry run')
        ->expectsOutputToContain(sprintf('Would merge: Rule #%d "First conditional" -> merge into #%d "First Conditional"', $s['duplicate']->id, $s['keep']->id))
        ->expectsOutputToContain(sprintf('Would merge: Content #%d', $s['adeleNew']->id))
        ->assertSuccessful();

    expect([GrammarRule::query()->pluck('status', 'id')->all(), ContentRuleLink::query()->count(), UserGrammarRule::query()->count(), Content::query()->pluck('status', 'id')->all()])
        ->toBe($before);
});

test('--apply merges the duplicate rule without losing links, examples, hides or learner progress', function () {
    $s = seedIncident();

    $this->artisan('catalog:merge-duplicates --apply')->assertSuccessful();

    $keep = $s['keep']->refresh();
    $duplicate = $s['duplicate']->refresh();

    expect($duplicate->status)->toBe(GrammarRule::STATUS_ARCHIVED)
        ->and($keep->status)->toBe(GrammarRule::STATUS_PUBLISHED)
        // Only gaps are filled from the duplicate.
        ->and($keep->summary)->toBe('Real future possibility')
        ->and($keep->level)->toBe('B1');

    // Content links: one per content, all on the kept rule.
    expect(ContentRuleLink::query()->where('grammar_rule_id', $duplicate->id)->count())->toBe(0)
        ->and(ContentRuleLink::query()->where('grammar_rule_id', $keep->id)->pluck('content_id')->sort()->values()->all())
        ->toBe([$s['contentA']->id, $s['contentB']->id]);

    // Identical sentence kept once; the learner's hide follows it. Own sentence moved, not primary.
    expect(GrammarRuleExample::query()->find($s['dupTwin']->id))->toBeNull()
        ->and(GrammarRuleExampleHide::query()->where('user_id', $s['user']->id)->pluck('grammar_rule_example_id')->all())->toBe([$s['keptTwin']->id])
        ->and($s['dupOwn']->refresh()->grammar_rule_id)->toBe($keep->id)
        ->and($s['dupOwn']->is_primary)->toBeFalse()
        ->and(GrammarRuleExample::query()->where('grammar_rule_id', $keep->id)->where('is_primary', true)->count())->toBe(1);

    expect($s['exercise']->refresh()->grammar_rule_id)->toBe($keep->id)
        ->and(GrammarRuleEmbedding::query()->where('grammar_rule_id', $duplicate->id)->exists())->toBeFalse();

    // Two progress rows become one: learned wins, earliest dates, manual confidence kept.
    $progress = UserGrammarRule::query()->where('user_id', $s['user']->id)->get();
    expect($progress)->toHaveCount(1)
        ->and($progress->first()->grammar_rule_id)->toBe($keep->id)
        ->and($progress->first()->status)->toBe('learned')
        ->and($progress->first()->started_at->toDateTimeString())->toBe('2026-08-10 10:00:00')
        ->and($progress->first()->learned_at->toDateTimeString())->toBe('2026-08-25 10:00:00')
        ->and($progress->first()->confidence_manual)->toBe(0.8);

    // Unused duplicate video rejected (not deleted), the original untouched.
    expect($s['adeleNew']->refresh()->status)->toBe('rejected')
        ->and($s['adeleNew']->moderation_comment)->toContain('Duplicate of #'.$s['adeleOld']->id)
        ->and($s['adeleNew']->transcriptSegments()->count())->toBe(1)
        ->and($s['adeleOld']->refresh()->status)->toBe('ready');

    // Idempotent.
    $this->artisan('catalog:merge-duplicates --apply')->expectsOutputToContain('No duplicates found.')->assertSuccessful();
});

test('the copy a learner used is kept even when it is the newer one', function () {
    $user = User::factory()->create();
    $old = mergeVideo('https://www.youtube.com/watch?v=hLQl3WQQoQ0');
    $used = mergeVideo('https://youtu.be/hLQl3WQQoQ0');
    DB::table('learning_progress')->insert(['user_id' => $user->id, 'content_id' => $used->id, 'created_at' => now(), 'updated_at' => now()]);

    $this->artisan('catalog:merge-duplicates --apply')->assertSuccessful();

    expect($old->refresh()->status)->toBe('rejected')
        ->and($used->refresh()->status)->toBe('ready');
});

test('copies of one video that both carry learner data are left for a manual merge', function () {
    $user = User::factory()->create();
    $a = mergeVideo('https://www.youtube.com/watch?v=hLQl3WQQoQ0');
    $b = mergeVideo('https://youtu.be/hLQl3WQQoQ0');
    foreach ([$a, $b] as $content) {
        DB::table('learning_progress')->insert(['user_id' => $user->id, 'content_id' => $content->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    $this->artisan('catalog:merge-duplicates --apply')->expectsOutputToContain('merge them by hand')->assertFailed();

    expect(Content::query()->where('status', 'ready')->count())->toBe(2);
});

test('a reference the merge does not know about rolls the whole rule merge back', function () {
    $keep = mergeRule('Passive Voice');
    $duplicate = mergeRule('Passive voice');
    ContentRuleLink::query()->create(['content_id' => mergeVideo('https://youtu.be/FWTNMzK9vG4')->id, 'grammar_rule_id' => $duplicate->id, 'status' => 'linked']);
    Schema::create('future_rule_notes', function ($table): void {
        $table->id();
        $table->foreignId('grammar_rule_id')->constrained('grammar_rules');
    });
    DB::table('future_rule_notes')->insert(['grammar_rule_id' => $duplicate->id]);

    $this->artisan('catalog:merge-duplicates --apply')
        ->expectsOutputToContain('still referenced by future_rule_notes.grammar_rule_id')
        ->assertFailed();

    expect($duplicate->refresh()->status)->toBe(GrammarRule::STATUS_PUBLISHED)
        ->and(ContentRuleLink::query()->where('grammar_rule_id', $duplicate->id)->count())->toBe(1);
});

test('near-duplicates are merged only when named explicitly, and bad pairs are refused', function () {
    $keep = mergeRule('Past Simple');
    $near = mergeRule('Simple Past Tense');
    $german = mergeRule('Präteritum', ['language' => 'de']);

    $this->artisan('catalog:merge-duplicates --apply')->expectsOutputToContain('No duplicates found.')->assertSuccessful();
    expect($near->refresh()->status)->toBe(GrammarRule::STATUS_PUBLISHED);

    $this->artisan('catalog:merge-duplicates', ['--apply' => true, '--rule' => ["{$keep->id}:{$german->id}"]])
        ->expectsOutputToContain('different languages')->assertFailed();
    $this->artisan('catalog:merge-duplicates', ['--rule' => ['14-26']])->expectsOutputToContain('Invalid --rule')->assertFailed();

    $this->artisan('catalog:merge-duplicates', ['--apply' => true, '--rule' => ["{$keep->id}:{$near->id}"]])->assertSuccessful();
    expect($near->refresh()->status)->toBe(GrammarRule::STATUS_ARCHIVED);
});

test('every table referencing grammar rules is one the merge handles', function () {
    // A new table keyed by a grammar rule must be added to GrammarRuleMerger
    // or a GrammarRuleMergeParticipant; the runtime check would otherwise
    // refuse every merge of a rule with rows in it.
    $tables = collect(app(ForeignKeyGraph::class)->referencesTo('grammar_rules'))
        ->map(fn (array $reference): string => $reference['table'].'.'.$reference['column'])->sort()->values()->all();

    expect($tables)->toBe([
        'content_grammar_candidates.matched_grammar_rule_id',
        'content_rule_links.grammar_rule_id',
        'grammar_exam_attempts.grammar_rule_id',
        'grammar_rule_embeddings.grammar_rule_id',
        'grammar_rule_example_generations.grammar_rule_id',
        'grammar_rule_examples.grammar_rule_id',
        'grammar_rule_exercises.grammar_rule_id',
        'grammar_rule_lexeme.grammar_rule_id',
        'lesson_grammar_candidates.matched_grammar_rule_id',
        'user_grammar_rules.grammar_rule_id',
    ]);
});
