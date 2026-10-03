# Start learning: implemented flow inventory

Repository inventory for VIK-29, 2026-10-03. These are source-code facts, not evidence that a mode improves learning. Database-configured profiles can differ from these defaults; this inventory does not inspect production data.

## Learning profiles

All five seeded profiles are published, version 1. Weights below are recognition / recall / production / listening / speaking. Defaults define encounter=1, recognition=5, recall=10, production=20, listening=15, dictation=20, speaking=25, shadowing=25 base points; CEFR multipliers A1=.8, A2=1, B1=1.2, B2=1.5, C1=1.8, C2=2.2. Sources: [defaults](../../src/app/Modules/Learning/Application/LearningFlowDefaults.php), [seeder](../../src/database/seeders/LearningFlowProfileSeeder.php).

| Profile | Configured stages | Weights | Minutes / new words | Success target |
| --- | --- | --- | --- | --- |
| Balanced | encounter → recognition → recall → production → listening → speaking | 20/30/25/15/10 | 15 / 8 | 80% |
| Listening first | encounter → listening → recognition → recall → dictation → production | 15/20/15/35/15 | 15 / 8 | 80% |
| Speaking first | encounter → recognition → recall → production → speaking → listening | 10/20/30/15/25 | 15 / 8 | 80% |
| Fast vocabulary | Balanced stages | 35/35/15/10/5 | 10 / 12 | 80% |
| Deep mastery | Balanced stages | 10/25/35/15/15 | 25 / 5 | 85% |

Resolution order is personal admin assignment → learner-selected published profile → matching language/level/goal assignment → Balanced fallback. User preferences override minutes/new words, listening/speaking weight, hints and difficulty. [Resolver](../../src/app/Modules/Learning/Application/LearningFlowResolver.php).

**Configured stages are not a literal trainer sequence.** The selector prioritizes the dimension associated with the latest error, otherwise lowest confidence minus profile weight. It separately labels stage: no attempts=encounter, confidence <35=recognition, <60=recall, <80=production, otherwise listening. Production selects cloze only with examples; speaking selects shadowing with examples, otherwise recall/recognition. The frontend maps recall/recognition to quick-check when translated, and encounter/shadowing to listen-recognize or listening: the adaptive queue does not actually present a recording-based shadowing card. The selector does not consume configured `stages` or `target_success_rate`. [Selector](../../src/app/Modules/Learning/Application/AdaptiveActivitySelector.php), [trainer session](../../src/resources/js/spa/composables/useTrainingSession.ts).

## Trainer modes and variants

The current start screen already offers Adaptive; Recall, Type it, Tap letters, Choose the word; Cloze, Listening, Dictation, Shadowing; Read & reveal, Write flexible, Write exact, Build the sentence; Context and Ready check. Dictation/shadowing/exam buttons require a content scope, but their buttons do not check actual transcript availability. [Repetitions page](../../src/resources/js/spa/pages/RepetitionsPage.vue).

| Mode / variant | Actual task and grading | Prerequisites / outcome |
| --- | --- | --- |
| Adaptive | Due reviews first, then new words, then content reinforcement. Global practice adds new words from the content with most unlearned words and does not add reinforcement. | Content-scoped queue uses up to 8 new + 6 reinforcement, plus due reviews; explicit content selection caps at 30 unlearned words and skips the mix. |
| Learn card | Material shown; Already know marks learned; Learn this word starts learning/SRS. | Choosing a new word is distinct from answering a retrieval question. |
| Review / reveal | Show answer → self-grade Again/Hard/Good/Easy. | Persists an SRS review immediately. |
| Review / type | Translation cues target word; local typed comparison → SRS grade. | Without translation the target remains visible, so this can become copying. |
| Review / tap letters | Fill roughly 75% of missing letters using shuffled tiles, with decoys from visible letters. | Local comparison; no alphabet service needed. |
| Choose the word | Starts focused cloze and sets global answer preference to choose-word. | Missing-word choices rather than sentence translation. |
| Cloze | Missing target form in sentence; choose-word uses distractors; other answer styles type or tap letters (`reveal` also means type here). | Generates/caches a sentence on card entry; generation failure exposes retry and blocks normal answer input. |
| Listening | Word audio → type/tap the word; even choose-word/reveal preferences use typing. | Browser speech synthesis; focused mode transforms all selected queue cards into listening. |
| Listen-recognize | Hear target word → reveal native translation manually/automatically → Knew it / Didn't know. | Self-report, not audio transcription; reveal is recorded as hint use. Missing native voice falls back to text. |
| Quick-check | Target word → select translation. | Distractors from reinforcement pool; remains multiple-choice irrespective of answer style. |

Sources for this table: [session queue and submit paths](../../src/resources/js/spa/composables/useTrainingSession.ts), [WordCard](../../src/resources/js/spa/widgets/trainer/WordCard.vue), [typed/letters input](../../src/resources/js/spa/widgets/trainer/TypedAnswerInput.vue), [mode routing](../../src/resources/js/spa/pages/RepetitionsPage.vue).

| Sentence / audio mode | Actual task | Availability and progress |
| --- | --- | --- |
| Context | Explicit word selection → native example sentence → optional written attempt → reveal target sentence → self-grade. | Reuses stored sentence+translation; missing/new example can be generated. Needs-work words come first. Submits self-check batch at session end. |
| Read & reveal | Generated sentence, tap or auto-reveal translation, then continue. | Uses AI generation but no answer grading or persisted mastery outcome. |
| Write flexible | Translate generated sentence naturally; AI grades meaning. | Optional browser microphone fills text; Listen only hides prompt text and uses browser speech. |
| Write exact | Same sentence task with exact grading requested. | Still uses sentence grading API, not trainer word comparison. |
| Build sentence | Arrange generated answer-sentence tokens; local normalized word-order comparison. | No server grading call and no persisted confidence/SRS outcome. |
| Transcript dictation | Select transcript segment, listen and type phrase; queued local text comparison. | Needs content and segments; does not need an LLM for checking typed text. |
| Transcript shadowing | Play segment, record repeat, upload audio; queued speech-to-text plus optional pronunciation assessment. | Requires microphone/provider availability; pronunciation assessment errors can fall back to text-comparison score. |
| Ready check | Mixed content words/grammar sentences; answer grading, final score/pass threshold and stored attempt. | Separate content exam flow with readiness blocking and retake; do not confuse with self-graded practice. |

Sources: [context session](../../src/resources/js/spa/composables/useContextPracticeSession.ts), [context card](../../src/resources/js/spa/widgets/trainer/ContextSentenceCard.vue), [sentence modes](../../src/resources/js/spa/pages/SpeakingPracticePage.vue), [sentence session](../../src/resources/js/spa/composables/useSentencePracticeSession.ts), [sentence service](../../src/app/Modules/Ai/Application/SentencePracticeService.php), [speaking card](../../src/resources/js/spa/widgets/trainer/SpeakingPracticeCard.vue), [transcript page](../../src/resources/js/spa/pages/TranscriptExercisePage.vue), [exercise processing](../../src/app/Modules/Learning/Application/ExerciseAttemptService.php), [exam page](../../src/resources/js/spa/pages/ContentExamPage.vue).

## Design pitfalls to preserve or resolve explicitly

- **Do not promise fixed profile-driven lengths or ordered stages from current execution.** Queue sizes and repeat placement are hardcoded (8 new, 6 reinforcement; one retry inserted four positions ahead), while profile minutes/new words/retry settings are displayed/configured separately. Focused Recall/Type/Letters still start the adaptive queue; they do not transform learn or reinforcement cards into review cards. [Session](../../src/resources/js/spa/composables/useTrainingSession.ts), [mode routing](../../src/resources/js/spa/pages/RepetitionsPage.vue).
- **No single progress meaning covers every mode.** Learned state, SRS review, dimension confidence, context self-check, local correct count and stored exam attempt differ. Sentence practice service is stateless; read/reorder only advance local state. Context submits answers without an exercise type, using legacy confidence recording; generic reinforcement also updates the context-check record. [Sentence session](../../src/resources/js/spa/composables/useSentencePracticeSession.ts), [sentence service](../../src/app/Modules/Ai/Application/SentencePracticeService.php), [self-check service](../../src/app/Modules/Learning/Application/SelfCheckService.php).
- **Points are conditional rewards, not a reliable cross-mode difficulty scale.** Award formula is base × CEFR × quality (score ≥90: 1; ≥60: .6; otherwise 0), with hint use multiplying by .6 again. Review/self-check fallback base is 10; quick-check fallback is 5; unconfigured cloze falls back to 10. Exercise points/progress are recorded only when an explicit content lexeme is attached; transcript segment alone is not automatically resolved to a lexeme. The transcript page nonetheless says the result updates progress/SRS. [Points](../../src/app/Modules/Learning/Application/PointsAwardService.php), [exercise service](../../src/app/Modules/Learning/Application/ExerciseAttemptService.php), [content gateway](../../src/app/Modules/Content/Application/ExerciseContentGateway.php), [transcript page](../../src/resources/js/spa/pages/TranscriptExercisePage.vue).
- **AI availability and browser audio are separate capabilities.** Existing review, word checks, stored context and typed dictation have non-LLM paths. Generated cloze/sentence modes require successful generation; sentence writing additionally requires grading. Speaking translation uses browser speech recognition, while shadowing uploads audio to server providers. The transcript dictation card's own Replay audio callback is not passed by its page; the separate Replay context control is wired. Transcript target text is visible in the surrounding page even when the shadowing card hides its target. [WordCard](../../src/resources/js/spa/widgets/trainer/WordCard.vue), [speaking card](../../src/resources/js/spa/widgets/trainer/SpeakingPracticeCard.vue), [dictation card](../../src/resources/js/spa/widgets/trainer/DictationCard.vue), [transcript page](../../src/resources/js/spa/pages/TranscriptExercisePage.vue).
- **Session completion can precede successful save.** Reinforcement and context batch results are saved at the end, and an error still leads to summary. Restart starts ordinary scope practice without retaining explicit IDs/focused mode. Design should make completion, save failure and restart scope legible. [Trainer session](../../src/resources/js/spa/composables/useTrainingSession.ts), [context session](../../src/resources/js/spa/composables/useContextPracticeSession.ts).

Verification: read-only source inspection and relative-link existence checks; no runtime/UI or production data verification.
