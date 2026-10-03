<?php

use App\Modules\Ai\Interfaces\Jobs\ComputeCanonicalLexemeEmbeddingsJob;
use App\Modules\Ai\Domain\Models\CanonicalLexemeEmbedding;
use App\Modules\Content\Domain\Models\Lexeme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.openai.com/*' => Http::response([
            'data' => [
                ['embedding' => array_fill(0, 1536, 0.01)],
            ],
        ], 200),
    ]);
    config(['ai.openai.api_key' => 'test-key']);
});

test('compute canonical lexeme embeddings job stores vectors for given lexeme ids', function () {
    $lex1 = Lexeme::query()->create([
        'slug' => 'en-hello', 'language' => 'en', 'lemma' => 'hello', 'normalized_lemma' => 'hello', 'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $lex2 = Lexeme::query()->create([
        'slug' => 'en-world', 'language' => 'en', 'lemma' => 'world', 'normalized_lemma' => 'world', 'status' => Lexeme::STATUS_PUBLISHED,
    ]);

    (new ComputeCanonicalLexemeEmbeddingsJob([$lex1->id, $lex2->id]))->handle(app(\App\Contracts\Ai\EmbeddingsClientInterface::class), app(\App\Modules\Content\Application\Contracts\EmbeddingSourceReaderInterface::class));

    expect(CanonicalLexemeEmbedding::query()->count())->toBe(2);
    $row = CanonicalLexemeEmbedding::query()->where('lexeme_id', $lex1->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->embedding)->toHaveCount(1536)
        ->and($row->model_version)->toBe('text-embedding-3-small');
});

test('compute canonical lexeme embeddings job is idempotent', function () {
    $lex = Lexeme::query()->create([
        'slug' => 'en-word', 'language' => 'en', 'lemma' => 'word', 'normalized_lemma' => 'word', 'status' => Lexeme::STATUS_PUBLISHED,
    ]);

    $embeddings = app(\App\Contracts\Ai\EmbeddingsClientInterface::class);
    (new ComputeCanonicalLexemeEmbeddingsJob([$lex->id]))->handle($embeddings, app(\App\Modules\Content\Application\Contracts\EmbeddingSourceReaderInterface::class));
    (new ComputeCanonicalLexemeEmbeddingsJob([$lex->id]))->handle($embeddings, app(\App\Modules\Content\Application\Contracts\EmbeddingSourceReaderInterface::class));

    expect(CanonicalLexemeEmbedding::query()->where('lexeme_id', $lex->id)->count())->toBe(1);
});
