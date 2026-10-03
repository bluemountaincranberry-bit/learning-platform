<?php

/**
 * Golden dataset for the `ai:eval --suite=content_analysis` command (task
 * 5.1). Each case is a short transcript with a manually curated set of
 * words/phrases and grammar constructions a reasonable analysis should
 * surface — not exhaustive, just "the obvious ones a human reviewer would
 * expect", so a prompt regression shows up as a recall drop instead of only
 * being noticed by eyeballing admin output.
 *
 * Matching is case-insensitive and allows either direction of substring
 * match (e.g. AI returning "give up on it" still matches expected "give
 * up") — see AiEvalCommand::matchCount(). This is intentionally forgiving:
 * the goal is tracking whether the *concept* was found, not exact string
 * equality with a hand-written phrase.
 */
return [
    [
        'name' => 'business-negotiation',
        'source_language' => 'en',
        'translation_language' => 'ru',
        'transcript' => <<<'TEXT'
            We have been negotiating this contract for three weeks, and the deadline
            is approaching fast. If we don't collaborate more closely with the
            supplier, we will miss it entirely. Yesterday, our manager decided to
            postpone the meeting until Friday, which gives the legal team a bit more
            breathing room to review the terms.
            TEXT,
        'expected_lexemes' => ['negotiate', 'deadline', 'collaborate', 'postpone'],
        'expected_grammar' => ['Present Perfect'],
    ],
    [
        'name' => 'daily-routine-past-simple',
        'source_language' => 'en',
        'translation_language' => 'ru',
        'transcript' => <<<'TEXT'
            Yesterday I woke up early, made breakfast, and walked to the station.
            I usually take the bus, but the weather was nice so I decided to walk
            instead. On the way, I bumped into an old friend I hadn't seen in years,
            and we ended up talking for almost half an hour.
            TEXT,
        'expected_lexemes' => ['bump into', 'end up'],
        'expected_grammar' => ['Past Simple'],
    ],
    [
        'name' => 'travel-conditionals',
        'source_language' => 'en',
        'translation_language' => 'ru',
        'transcript' => <<<'TEXT'
            If I had more vacation days, I would travel to Japan next spring to see
            the cherry blossoms. My colleague went there last year and said that if
            you don't book accommodation months in advance, you end up paying twice
            as much. It's definitely worth planning ahead.
            TEXT,
        'expected_lexemes' => ['accommodation', 'in advance'],
        'expected_grammar' => ['Second Conditional'],
    ],
];
