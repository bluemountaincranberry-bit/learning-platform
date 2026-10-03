<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Ai\LexemeEnrichmentPromptBuilder;
use App\Modules\Content\Application\Contracts\LexemeEnrichmentCatalogInterface;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Domain\Models\LexemeAssociation;
use Illuminate\Support\Str;

final class LexemeEnrichmentCatalog implements LexemeEnrichmentCatalogInterface
{
    public function __construct(private readonly LexemeEnrichmentPromptBuilder $promptBuilder) {}

    public function promptContext(int $lexemeId): ?array
    {
        $lexeme = Lexeme::query()->find($lexemeId);
        if ($lexeme === null) {
            return null;
        }

        return [
            'language' => $lexeme->language ?? 'en',
            'subject_type' => Lexeme::class,
            'subject_id' => $lexeme->id,
            'prompt' => $this->promptBuilder->buildPrompt($lexeme, null),
            'relation_types' => LexemeAssociation::TYPES,
        ];
    }

    public function applyAccepted(int $lexemeId, array $data): array
    {
        $lexeme = Lexeme::query()->findOrFail($lexemeId);
        $created = ['related' => 0, 'examples' => 0, 'translations' => 0];

        foreach ($data['related'] ?? [] as $row) {
            if (empty($row['accept']) || blank($row['lemma'] ?? null)) {
                continue;
            }

            $relatedLexemeId = $row['matched_lexeme_id'] ?? null;
            if ($relatedLexemeId === null) {
                $lemma = trim((string) $row['lemma']);
                $related = Lexeme::query()->firstOrCreate(
                    ['language' => $lexeme->language, 'normalized_lemma' => Str::lower($lemma)],
                    [
                        'slug' => Str::slug($lexeme->language.'-'.$lemma) ?: Str::lower($lexeme->language.'-lexeme-'.Str::random(8)),
                        'lemma' => $lemma,
                        'status' => Lexeme::STATUS_DRAFT,
                    ]
                );
                $relatedLexemeId = $related->id;
            }

            if ($relatedLexemeId === $lexeme->id) {
                continue;
            }

            $type = is_string($row['type'] ?? null) && in_array($row['type'], LexemeAssociation::TYPES, true)
                ? $row['type']
                : LexemeAssociation::TYPE_RELATED;

            LexemeAssociation::query()->firstOrCreate([
                'lexeme_id' => $lexeme->id,
                'related_lexeme_id' => $relatedLexemeId,
                'type' => $type,
            ]);
            $created['related']++;
        }

        foreach ($data['examples'] ?? [] as $row) {
            if (empty($row['accept']) || blank($row['example'] ?? null)) {
                continue;
            }

            $hasPrimary = $lexeme->examples()->where('is_primary', true)->exists();
            $lexeme->examples()->create([
                'language' => $lexeme->language,
                'example' => $row['example'],
                'translation' => $row['translation'] ?? null,
                'is_primary' => ! $hasPrimary,
                'sort_order' => 0,
            ]);
            $created['examples']++;
        }

        foreach ($data['translations'] ?? [] as $row) {
            if (empty($row['accept']) || blank($row['translation'] ?? null) || blank($row['language'] ?? null)) {
                continue;
            }

            $hasPrimary = $lexeme->translations()->where('language', $row['language'])->where('is_primary', true)->exists();
            $lexeme->translations()->create([
                'language' => $row['language'],
                'translation' => $row['translation'],
                'is_primary' => ! $hasPrimary,
                'sort_order' => 0,
            ]);
            $created['translations']++;
        }

        return $created;
    }
}
