<?php

namespace App\Contracts\Ai;

/**
 * Settings an AI analysis run was launched with. The only place that knows
 * the shape of `ai_analysis_runs.config` — a new setting means a new
 * constructor param handled here, not a schema change.
 */
final class AiAnalysisRunConfig
{
    public const THOROUGHNESS_FOCUSED = 'focused';

    public const THOROUGHNESS_THOROUGH = 'thorough';

    public const THOROUGHNESS_LEVELS = [
        self::THOROUGHNESS_FOCUSED,
        self::THOROUGHNESS_THOROUGH,
    ];

    /**
     * Short curated list for language-select inputs (admin AI-run form,
     * admin's own profile default) — not an exhaustive ISO 639-1 list.
     *
     * @var array<string, string>
     */
    public const TRANSLATION_LANGUAGES = [
        'ru' => 'Русский',
        'en' => 'English',
        'es' => 'Español',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'it' => 'Italiano',
        'pt' => 'Português',
        'zh' => '中文',
        'ja' => '日本語',
        'ko' => '한국어',
        'ar' => 'العربية',
        'tr' => 'Türkçe',
        'pl' => 'Polski',
        'nl' => 'Nederlands',
        'uk' => 'Українська',
    ];

    /**
     * @param  array<int, string>  $excludeWords
     * @param  array<int, int>  $excludeGrammarRuleIds
     */
    public function __construct(
        public readonly ?string $targetLevel = null,
        public readonly array $excludeWords = [],
        public readonly array $excludeGrammarRuleIds = [],
        public readonly ?string $extraInstructions = null,
        public readonly string $thoroughness = self::THOROUGHNESS_FOCUSED,
        public readonly ?string $translationLanguage = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $targetLevel = $data['target_level'] ?? null;
        $translationLanguage = $data['translation_language'] ?? null;

        return new self(
            targetLevel: is_string($targetLevel) && $targetLevel !== '' ? $targetLevel : null,
            excludeWords: self::parseWordList($data['exclude_words'] ?? []),
            excludeGrammarRuleIds: array_values(array_map('intval', array_filter((array) ($data['exclude_grammar_rule_ids'] ?? [])))),
            extraInstructions: is_string($data['extra_instructions'] ?? null) && trim($data['extra_instructions']) !== ''
                ? trim($data['extra_instructions'])
                : null,
            thoroughness: in_array($data['thoroughness'] ?? null, self::THOROUGHNESS_LEVELS, true)
                ? $data['thoroughness']
                : self::THOROUGHNESS_FOCUSED,
            translationLanguage: is_string($translationLanguage) && $translationLanguage !== '' ? $translationLanguage : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'target_level' => $this->targetLevel,
            'exclude_words' => $this->excludeWords,
            'exclude_grammar_rule_ids' => $this->excludeGrammarRuleIds,
            'extra_instructions' => $this->extraInstructions,
            'thoroughness' => $this->thoroughness,
            'translation_language' => $this->translationLanguage,
        ];
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private static function parseWordList($value): array
    {
        if (is_array($value)) {
            $words = $value;
        } elseif (is_string($value)) {
            $words = preg_split('/[,\n]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        } else {
            $words = [];
        }

        $words = array_map(fn ($word) => trim((string) $word), $words);

        return array_values(array_filter($words, fn (string $word) => $word !== ''));
    }
}
