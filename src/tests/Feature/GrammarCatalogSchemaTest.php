<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\User\Models\User;
use Database\Seeders\GrammarCatalogSeeder;
use Illuminate\Support\Facades\Schema;

test('grammar catalog tables are available with core columns', function () {
    expect(Schema::hasTable('grammar_topics'))->toBeTrue()
        ->and(Schema::hasTable('grammar_rules'))->toBeTrue()
        ->and(Schema::hasTable('lexemes'))->toBeTrue()
        ->and(Schema::hasTable('grammar_rule_lexeme'))->toBeTrue()
        ->and(Schema::hasTable('grammar_rule_examples'))->toBeTrue()
        ->and(Schema::hasTable('lexeme_examples'))->toBeTrue()
        ->and(Schema::hasTable('lexeme_associations'))->toBeTrue()
        ->and(Schema::hasTable('content_rule_links'))->toBeTrue();

    expect(Schema::hasColumns('grammar_topics', ['slug', 'language', 'name', 'status', 'sort_order']))->toBeTrue()
        ->and(Schema::hasColumns('grammar_rules', ['topic_id', 'slug', 'title', 'status', 'level']))->toBeTrue()
        ->and(Schema::hasColumns('lexemes', ['slug', 'lemma', 'normalized_lemma', 'part_of_speech', 'status']))->toBeTrue()
        ->and(Schema::hasColumns('content_lexemes', ['content_id', 'lexeme_id', 'type', 'text']))->toBeTrue()
        ->and(Schema::hasColumns('user_lexeme_progress', ['user_id', 'content_lexeme_id', 'lexeme_id', 'learned_at']))->toBeTrue();
});

test('grammar topic, rules, lexemes and content links work through eloquent relations', function () {
    $topic = GrammarTopic::query()->create([
        'slug' => 'conditionals',
        'language' => 'en',
        'name' => 'Conditionals',
        'status' => GrammarTopic::STATUS_ACTIVE,
        'sort_order' => 10,
    ]);

    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id,
        'slug' => 'first-conditional',
        'language' => 'en',
        'title' => 'First conditional',
        'status' => GrammarRule::STATUS_PUBLISHED,
        'level' => 'B1',
    ]);

    $lexeme = Lexeme::query()->create([
        'slug' => 'if-conjunction',
        'language' => 'en',
        'lemma' => 'if',
        'normalized_lemma' => 'if',
        'part_of_speech' => 'conjunction',
        'status' => Lexeme::STATUS_PUBLISHED,
        'level' => 'A2',
    ]);

    $rule->lexemes()->attach($lexeme->id, ['sort_order' => 10]);
    $rule->examples()->create([
        'language' => 'en',
        'example' => 'If it rains, we will stay home.',
        'is_primary' => true,
        'sort_order' => 10,
    ]);
    $lexeme->examples()->create([
        'language' => 'en',
        'example' => 'Call me if you need help.',
        'is_primary' => true,
        'sort_order' => 10,
    ]);

    $relatedLexeme = Lexeme::query()->create([
        'slug' => 'unless-conjunction',
        'language' => 'en',
        'lemma' => 'unless',
        'normalized_lemma' => 'unless',
        'part_of_speech' => 'conjunction',
        'status' => Lexeme::STATUS_REVIEW,
        'level' => 'B1',
    ]);

    $lexeme->associations()->create([
        'related_lexeme_id' => $relatedLexeme->id,
        'type' => 'contrast',
        'sort_order' => 10,
    ]);

    $content = Content::query()->create([
        'type' => 'grammar',
        'title' => 'First Conditional Drill',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'draft',
    ]);
    $contentLexeme = $content->lexemes()->create([
        'type' => ContentLexeme::TYPE_WORD,
        'text' => 'if',
        'sort_order' => 1,
    ]);

    $rule->contentLinks()->create([
        'content_id' => $content->id,
        'status' => 'linked',
    ]);
    $user = User::query()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
    UserLexemeProgress::query()->create([
        'user_id' => $user->id,
        'content_lexeme_id' => $contentLexeme->id,
        'learned_at' => now(),
    ]);

    $topic->refresh();
    $rule->refresh();
    $lexeme->refresh();
    $content->refresh();

    expect($topic->rules)->toHaveCount(1)
        ->and($rule->lexemes)->toHaveCount(1)
        ->and($rule->examples)->toHaveCount(1)
        ->and($lexeme->examples)->toHaveCount(1)
        ->and($lexeme->associations)->toHaveCount(1)
        ->and($content->grammarRules)->toHaveCount(1)
        ->and($content->lexemes)->toHaveCount(1)
        ->and($contentLexeme->lexeme_id)->toBe($lexeme->id)
        ->and(UserLexemeProgress::query()->where('lexeme_id', $lexeme->id)->count())->toBe(1);
});

test('grammar catalog seeder creates starter topics rules and lexemes', function () {
    $this->seed(GrammarCatalogSeeder::class);

    expect(GrammarTopic::query()->count())->toBeGreaterThanOrEqual(3)
        ->and(GrammarRule::query()->count())->toBeGreaterThanOrEqual(3)
        ->and(Lexeme::query()->count())->toBeGreaterThanOrEqual(5);

    $articles = GrammarTopic::query()->where('slug', 'articles-and-determiners')->first();
    $rule = GrammarRule::query()->where('slug', 'basic-articles-a-an-the')->first();
    $lexeme = Lexeme::query()->where('slug', 'the-article')->first();

    expect($articles)->not->toBeNull()
        ->and($rule)->not->toBeNull()
        ->and($lexeme)->not->toBeNull()
        ->and($rule?->topic?->is($articles))->toBeTrue()
        ->and($rule?->lexemes->pluck('slug')->contains('the-article'))->toBeTrue()
        ->and($lexeme?->examples)->toHaveCount(1);
});
