<?php

/**
 * Golden rules for `ai:eval --suite=grammar_examples` (VIK-39): does
 * AiGrammarRuleExampleService write examples that actually use the rule?
 *
 * Rubric, checked per rule on the stored examples (8 requested):
 * - at least `min_examples` examples survive Content's validation;
 * - every example has a marked target form and a translation;
 * - "uses the rule": the sentence matches `target_pattern` and every
 *   `sentence_patterns` entry — for at least 80% of the examples;
 * - "form marked" (reported, not gating): the marked text (spans joined
 *   with spaces) matches `target_pattern` — gpt-4o-mini often marks only
 *   the main verb of a question ("Was the job **done**?");
 * - kinds cover affirmative, negative and question;
 * - no stored (correct) sentence matches `forbidden_patterns` — the rule's
 *   typical errors; a single hit fails the rule. Catches a swapped mistake pair (the wrong sentence
 *   stored as the correct one), seen with real output.
 *
 * A failed case prints its off-rule examples, so a prompt change can be
 * judged by reading them, not only by the score.
 *
 * Patterns are deliberately loose (the form's tell-tale words, not a
 * parser): the eval catches a prompt that drifts off the rule or stops
 * marking, not stylistic choices.
 */
return [
    [
        'name' => 'present-perfect',
        'title' => 'Present Perfect',
        'level' => 'A2',
        'summary' => 'have/has + past participle: past actions connected to now (experience, recent news, unfinished time).',
        'target_pattern' => "/\\b(have|has|haven't|hasn't|have not|has not)\\b|'ve\\b|'s\\b/i",
        'sentence_patterns' => [],
        'forbidden_patterns' => [],
        'min_examples' => 6,
    ],
    [
        'name' => 'present-continuous',
        'title' => 'Present Continuous',
        'level' => 'A1',
        'summary' => 'am/is/are + -ing: actions happening now or around now, and temporary situations.',
        'target_pattern' => '/\\b\\w+ing\\b/i',
        'sentence_patterns' => ["/\\b(am|is|are|isn't|aren't)\\b|'m\\b|'s\\b|'re\\b/i"],
        'forbidden_patterns' => [],
        'min_examples' => 6,
    ],
    [
        'name' => 'first-conditional',
        'title' => 'First Conditional',
        'level' => 'B1',
        'summary' => 'If + present simple, will + infinitive: a real, likely condition in the future and its result.',
        'target_pattern' => "/\\b(if|unless|will|won't)\\b|'ll\\b/i",
        'sentence_patterns' => ['/\\b(if|unless)\\b/i', "/\\b(will|won't)\\b|'ll\\b/i"],
        // "If it will rain" — will inside the if-clause.
        'forbidden_patterns' => ["/\\b(if|unless)\\b[^,?.]*\\b(will|won't)\\b[^,?.]*,/i"],
        'min_examples' => 6,
    ],
    [
        'name' => 'passive-voice',
        'title' => 'Passive Voice (present and past simple)',
        'level' => 'B1',
        'summary' => 'be + past participle: the action matters more than who does it ("The bridge was built in 1900").',
        'target_pattern' => "/\\b(am|is|are|was|were|be|been|being|isn't|aren't|wasn't|weren't)\\b.*\\s\\w+/i",
        'sentence_patterns' => [],
        'forbidden_patterns' => [],
        'min_examples' => 6,
    ],
    [
        // Real drift seen in the first backfill: "They invited us to the
        // party" filed under reported speech, only "said" marked.
        'name' => 'reported-speech',
        'title' => 'Reported speech',
        'level' => 'B1',
        'summary' => 'Reporting what someone said: said/told/asked + (that/if) + clause, usually with a tense backshift ("She said she was tired").',
        // The model may mark the reporting structure ("said that", "asked if")
        // or the backshifted verb ("was tired") — both show the rule.
        'target_pattern' => "/\\b(said|told|asked|explained|wondered|was|were|had|would|could|did|didn't|wasn't|weren't|hadn't|wouldn't|couldn't|if|whether)\\b|\\w+ed\\b/i",
        'sentence_patterns' => ['/\\b(said|says|told|tells|asked|asks|explained|wanted to know|wondered)\\b/i'],
        // "told that" (no object), "asked that he could".
        'forbidden_patterns' => ['/\\btold\\s+that\\b/i', '/\\basked\\s+that\\b/i'],
        'min_examples' => 6,
    ],
    [
        'name' => 'can-for-ability',
        'title' => 'Can for ability',
        'level' => 'A1',
        'summary' => 'can/can\'t + infinitive: what someone is able or not able to do.',
        'target_pattern' => "/\\b(can|can't|cannot|could|couldn't)\\b/i",
        'sentence_patterns' => [],
        'forbidden_patterns' => [],
        'min_examples' => 6,
    ],
];
