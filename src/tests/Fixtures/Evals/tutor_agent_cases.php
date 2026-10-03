<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\User\Models\User;

/**
 * Golden scenarios for the `ai:eval --suite=tutor_agent` command (task
 * 5.1). Each scenario is a single user message the real
 * `StudentTutorAgentService` tool set should answer by calling one of the
 * `expected_tools` (grounding, not guessing — see
 * docs/architecture/ai-platform-vision.md section 4) rather than making the
 * answer up. `setup` seeds exactly the DB rows that tool needs so the eval
 * exercises a real, non-empty tool result, not just "did it call the right
 * function name on empty data".
 *
 * `expected_keywords` is a softer, secondary signal (checked against the
 * model's final reply) — useful context in the report, not part of the
 * pass/fail decision, since the model is free to phrase things differently
 * even when it grounded the answer correctly.
 */
return [
    [
        'name' => 'level-question',
        'message' => 'What CEFR level am I at right now, based on what I have learned?',
        'expected_tools' => ['get_user_level'],
        'expected_keywords' => ['b1', 'level'],
        'setup' => function (User $user): void {
            $content = Content::factory()->create();
            $lexeme = Lexeme::query()->create([
                'slug' => 'eval-gate-'.uniqid('', true),
                'language' => 'en',
                'lemma' => 'gate',
                'normalized_lemma' => 'gate',
                'status' => 'published',
                'level' => 'B1',
            ]);
            $contentLexeme = $content->lexemes()->create([
                'type' => ContentLexeme::TYPE_WORD,
                'text' => 'gate',
                'sort_order' => 1,
                'lexeme_id' => $lexeme->id,
            ]);
            UserLexemeProgress::query()->create([
                'user_id' => $user->id,
                'content_lexeme_id' => $contentLexeme->id,
                'lexeme_id' => $lexeme->id,
                'learned_at' => now(),
            ]);
        },
    ],
    [
        'name' => 'review-schedule-question',
        'message' => 'What do I have due for review today?',
        'expected_tools' => ['get_review_schedule'],
        'expected_keywords' => ['review', 'due'],
        'setup' => function (User $user): void {
            $content = Content::factory()->create();
            SrsCard::query()->create([
                'user_id' => $user->id,
                'content_id' => $content->id,
                'item_key' => 'word:eval-run',
                'state' => 'reviewing',
                'interval_days' => 2,
                'ease_factor' => 2.5,
                'next_review_at' => now()->subMinute(),
            ]);
        },
    ],
    [
        'name' => 'grammar-explanation-question',
        'message' => 'Can you explain what the present perfect tense is used for?',
        'expected_tools' => ['explain_grammar'],
        'expected_keywords' => ['present perfect'],
        'setup' => function (User $user): void {},
    ],
];
