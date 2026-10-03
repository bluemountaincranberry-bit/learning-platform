<?php

use App\Modules\Ai\Interfaces\Jobs\ComputeEmbeddingsJob;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Ai\Domain\Models\LexemeEmbedding;
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

test('compute embeddings job stores vectors for given lexeme ids', function () {
    $content = Content::query()->create([
        'type' => 'song',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_text' => 'hello',
    ]);
    $lex1 = $content->lexemes()->create([
        'type' => ContentLexeme::TYPE_WORD,
        'text' => 'hello',
        'sort_order' => 1,
    ]);
    $lex2 = $content->lexemes()->create([
        'type' => ContentLexeme::TYPE_WORD,
        'text' => 'world',
        'sort_order' => 2,
    ]);

    (new ComputeEmbeddingsJob([$lex1->id, $lex2->id]))->handle(app(\App\Contracts\Ai\EmbeddingsClientInterface::class), app(\App\Modules\Content\Application\Contracts\EmbeddingSourceReaderInterface::class));

    expect(LexemeEmbedding::query()->count())->toBe(2);
    $row1 = LexemeEmbedding::query()->where('content_lexeme_id', $lex1->id)->first();
    expect($row1)->not->toBeNull()
        ->and($row1->embedding)->toBeArray()
        ->and($row1->embedding)->toHaveCount(1536)
        ->and($row1->model_version)->toBe('text-embedding-3-small');
});

test('compute embeddings job is idempotent', function () {
    $content = Content::query()->create([
        'type' => 'song',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lex = $content->lexemes()->create([
        'type' => ContentLexeme::TYPE_WORD,
        'text' => 'word',
        'sort_order' => 1,
    ]);

    $embeddings = app(\App\Contracts\Ai\EmbeddingsClientInterface::class);

    (new ComputeEmbeddingsJob([$lex->id]))->handle($embeddings, app(\App\Modules\Content\Application\Contracts\EmbeddingSourceReaderInterface::class));
    (new ComputeEmbeddingsJob([$lex->id]))->handle($embeddings, app(\App\Modules\Content\Application\Contracts\EmbeddingSourceReaderInterface::class));

    expect(LexemeEmbedding::query()->where('content_lexeme_id', $lex->id)->count())->toBe(1);
});
