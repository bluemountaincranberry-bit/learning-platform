<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\PersonalLexemeResolverInterface;
use App\Modules\Content\Application\Contracts\PersonalLexemeReconcilerInterface;
use App\Modules\Content\Domain\Models\Lexeme;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PersonalLexemeResolver implements PersonalLexemeResolverInterface
{
    public function __construct(private readonly PersonalLexemeReconcilerInterface $reconciler) {}

    public function resolveOrCreate(int $ownerUserId, string $language, string $lemma): array
    {
        $language = strtolower(trim($language));
        $lemma = trim($lemma);
        $normalized = Str::lower($lemma);

        if ($language === '' || $normalized === '') {
            throw new InvalidArgumentException('A language and non-empty lemma are required.');
        }

        $shared = Lexeme::query()
            ->whereNull('owner_user_id')
            ->where('language', $language)
            ->where('normalized_lemma', $normalized)
            ->first();

        if ($shared !== null) {
            $private = Lexeme::query()->where('owner_user_id', $ownerUserId)
                ->where('language', $language)->where('normalized_lemma', $normalized)->first();
            if ($private !== null) {
                $this->reconciler->reconcile($ownerUserId, (int) $private->id, (int) $shared->id);
            }

            return ['id' => (int) $shared->id, 'lemma' => (string) $shared->lemma, 'language' => (string) $shared->language, 'is_personal' => false];
        }

        $slugSuffix = Str::slug($language.'-'.$normalized) ?: 'lexeme';
        $baseSlug = 'private-u'.$ownerUserId.'-'.$slugSuffix;

        $lexeme = Lexeme::query()->firstOrCreate(
            ['owner_user_id' => $ownerUserId, 'language' => $language, 'normalized_lemma' => $normalized],
            [
                'slug' => $this->uniqueSlug($baseSlug),
                'lemma' => $lemma,
                'status' => Lexeme::STATUS_DRAFT,
                'level' => null,
                'notes' => null,
            ],
        );

        return ['id' => (int) $lexeme->id, 'lemma' => (string) $lexeme->lemma, 'language' => (string) $lexeme->language, 'is_personal' => true];
    }

    public function visibleLexemeId(int $userId, int $lexemeId): int
    {
        $lexeme = Lexeme::query()->visibleTo($userId)->findOrFail($lexemeId);

        return $lexeme->alias_to_lexeme_id === null ? (int) $lexeme->id : (int) $lexeme->alias_to_lexeme_id;
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;

        while (Lexeme::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
