# Speaking Coach: TED practice, recordings and useful feedback

Research for [VIK-45](https://linear.app/viktoryia/issue/VIK-45/research-speaking-coach-shadow-tedx-record-myself-track-pronunciation), 2026-10-03. Owner: Learning, with Content and AI application contracts. This is a proposal, not an implemented feature or authorization to activate paid services. Sources were checked on this date; account credentials, Azure region/tier and real-phone behavior remain unverified.

## Recommendation and decision boundary

Ship a phone-first **listen → repeat → replay → save → compare** loop using short TED/TEDx phrases. Default Medium mixes visible-text sentence repetition and short chunk shadowing; Easy is listen/read/repeat. Hard hides the target until reveal and offers a short retell within the 30-second recording bound; full one-minute talks stay on the roadmap. A useful first version works without AI: the learner can listen to the speaker, record herself, keep selected recordings and hear an earlier and current take of the same phrase. Optional provider feedback adds accuracy/fluency, evidence-linked tips and comparable trends only after budget approval.

Use **three implementation tickets**, plus a separate human budget gate for the paid part. No self-video in MVP: audio serves pronunciation, rhythm and replay; camera introduces storage and consent work before adding demonstrated value. No accent-erasure promise, celebrity voice similarity score, pitch-overlay grade or single “beautiful speech” percentage. Vika can choose expressive speech as her goal while progress is evidenced by recordings, intelligibility and specific skills.

Proposed defaults: explicit Save recording; 90-day expiry for newly saved unpinned recordings, visible before consent and on each item; Pin for comparison within a 100 MB total private quota; no silent eviction. Existing learning data and recordings are untouched. These are product-owner assumptions for review, not destructive cleanup instructions. Paid service activation/budget is the only required owner question here; video can remain deferred without blocking research or basic replay.

## 1. Practice formats and learning evidence

The studies support focused repeated practice, but do not validate this app's future scores or guarantee transfer to spontaneous speech.

| Evidence | Finding and limitation | Product implication (our inference) |
|---|---|---|
| [Foote & McDonough, 2017, author-hosted paper](https://www.cal-lab.ca/uploads/1/3/5/8/135818832/foote___mcdonough_2017.pdf), DOI 10.1075/jslp.3.1.02foo | Sixteen advanced learners shadowed dialogues for eight weeks, at least four 10-minute sessions weekly; 22 listeners rated controlled and extemporaneous tasks. Imitation, comprehensibility and fluency improved; accentedness did not. Small self-selected sample, attrition and no randomized control limit causal claims and generalization to TED material. | Short recording/repetition is plausible; measure “easier to understand” separately from accent. Include an unpracticed speaking sample later to test transfer. |
| [de Jong & Perfetti, 2011, publisher abstract](https://onlinelibrary.wiley.com/doi/abs/10.1111/j.1467-9922.2010.00620.x) | Twenty-four ESL students delivered 4/3/2-minute speeches in three training sessions. Both groups improved during training; only same-speech repetition maintained fluency on posttests. Small classroom study with substantially longer tasks than our MVP. | Revisit one topic before changing it; a one-minute adaptation is a design hypothesis, not the tested protocol. |
| [Saito & Plonsky, 2019, authors' institutional record](https://discovery.ucl.ac.uk/id/eprint/10068780) | Meta-analysis of 77 pronunciation-teaching studies distinguishes controlled/spontaneous speech, discrete/global constructs and human/acoustic judgments. Strongest effectiveness concerned monitored production of specific features. | One actionable feature per retry; separate scripted accuracy from free-speech ability in the dashboard. |
| [Sakai & Moorman, perception-training meta-analysis](https://www.cambridge.org/core/journals/applied-psycholinguistics/article/abs/can-perception-training-improve-the-production-of-second-language-phonemes-a-metaanalytic-review-of-25-years-of-perception-training-research/57401D28450902EE96659AD10AA11488) | Synthesizes whether auditory phoneme training transfers to production; this is evidence for focused perception work, not a trial of our minimal-pair exercise. | Later drills should include hearing/discriminating a contrast before producing it, rather than demanding repetition alone. |

“Shadowing” here needs honest labels: **listen then repeat** is delayed imitation; **shadow with the speaker** is near-simultaneous production. Scored recordings should be solo speech with reference playback stopped, because speaker leakage can contaminate recognition. Headphones help when practicing actual shadowing.

| Format | Order / difficulty | Delivery |
|---|---|---|
| Read aloud after hearing the model | Easy | Visible text; 3–10-second phrase; one retry. MVP. |
| Sentence-by-sentence repeat | Easy → Medium | Preserve meaning, stress and linking; solo recorded take. MVP. |
| Chunk shadowing | Medium default | Two or three adjacent phrases, maximum 20-second reference; text can collapse after first listen. MVP. |
| Retell a short part in own words | Hard | Hide target until reveal, then optional retell up to 30 seconds; self-replay only in MVP. Never score against original wording. |
| Give a one-minute talk on the topic | Hard | Short preparation with 2–3 saved phrases, repeated topic, optional novel transfer sample. Later; requires longer capture/provider work. |
| Minimal pairs | Focus drill | Start only when repeated evidence identifies a sound contrast; listen/discriminate then speak. Later, not a default menu. |

Proposed session: five minutes; choose one content item; five phrases; listen, record, replay, optionally retry each; save one comparison take. Duration and count are assumptions, intentionally lighter than the research protocols. Follow VIK-29: one Continue action, Medium default, Easy/Medium/Hard and count in Change, optional settings out of the main path. Hard retell uses self-replay without scripted text-match grading; scored free speech remains later.

### First-party app patterns, not effectiveness endorsements

| Product and source | Advertised pattern | Adopt / avoid |
|---|---|---|
| [ELSA pronunciation FAQ](https://elsaspeak.com/en/faqs/how-does-elsas-pronunciation-feedback-work), [Speech Analyzer](https://speechanalyzer.elsaspeak.com/) | Sound/stress/intonation feedback; free-speech analysis includes pronunciation, fluency, grammar and vocabulary. | Distinct dimensions and short tips; avoid treating marketing scores as validation. |
| [Speechling method](https://speechling.com/), [Audio Journal guide](https://speechling.com/help/checking-feedback) | Listen, record, receive human feedback, repeat; journal compares learner/model and earlier recordings. | Audio journal and rerecord affordance are the closest MVP precedent. Human feedback is a useful later calibration option; no built-in human service is proposed. |
| [Praktika](https://praktika.ai/) | AI tutors, free conversation and gentle pronunciation/grammar/word-choice corrections. | Later topic practice and encouraging corrections; animated conversation is unnecessary for TED replay. |
| [YouGlish About](https://youglish.com/about) | Search phrases in real YouTube speech across accents and contexts. | Authentic examples and useful phrases; do not require a new embedded service in MVP. |
| [BoldVoice](https://boldvoice.com/) | Expert coaching videos, sound-level AI feedback, personalized practice and progress. | Explain what to change physically before retrying; do not promise native accent or license its instruction assets. |
| [Clozemaster's method](https://www.clozemaster.com/blog/cloze-tests-spaced-repetition-faster-language-learning/), [2026 comparison](https://www.clozemaster.com/blog/clozemaster-vs-rosetta-stone/) | Sentence cloze recall, listening and repetition; its own comparison says conversational fluency needs real interaction. | Reuse phrases in context and progressive difficulty. Historical forum Speaking-mode reports are not evidence of current platform-wide phoneme scoring. |

## 2. Turn timed TED transcripts into practice

Verified local foundation: [TranscriptSegment](../../src/app/Modules/Content/Domain/Models/TranscriptSegment.php) stores `start_ms`/nullable `end_ms`; [TranscriptSegmentStore](../../src/app/Modules/Content/Application/Transcript/TranscriptSegmentStore.php) persists source timings. Timed caption segments are not guaranteed complete sentences or word-aligned boundaries. [TranscriptExercisePage](../../src/resources/js/spa/pages/TranscriptExercisePage.vue) already calls segment replay; [YoutubeEmbed](../../src/resources/js/spa/shared/ui/YoutubeEmbed.vue) seeks with a small lead-in and pauses by a wall-clock timer. It exposes no speed or repeat-loop API today.

Proposed deterministic Content selection:

1. Read accepted transcript segments in source order; carry language, source IDs and exact text/timing snapshots into practice.
2. Merge adjacent fragments up to punctuation or a clear source boundary while keeping the total reference under 20 seconds. Reject missing/invalid ends, overlap, non-speech captions, music/applause-only entries and very long fragments. Never invent precise word timing by dividing caption duration evenly.
3. Prefer comprehensible, reusable phrases containing the learner's saved lexemes; diversify question/contrast/list constructions. Caption text alone cannot establish actual stress or linking: display these as practice targets only after listening/curation, or evidence-backed audio analysis later.
4. Let Vika select another phrase; do not mutate original transcript rows. If no suitable material exists, show the transcript and a clear unavailable state.

Use the existing embedded YouTube source rather than downloading TED audio. YouTube's [IFrame API](https://developers.google.com/youtube/iframe_api_reference) supports seeking and playback rates, but requested rates are not guaranteed and available rates must be queried. Add supported 0.75×/1× options; supervise loops using media time and player state, not the current timer alone (a slower rate changes elapsed time). Stop on recording/unmount; pause between repeats. On phone, start playback from a tap, handle blocked embedding and buffering, and keep recording available if the reference becomes unavailable. Exact timing needs manual checks; caption timing is not forced alignment. Downloading/licensing reference audio and pitch extraction are outside MVP.

## 3. Record, retain and replay privately

Current [useAudioRecorder](../../src/resources/js/spa/composables/useAudioRecorder.ts) requests audio only, probes WebM/Opus → WebM → MP4 → Ogg, and stops at 30 seconds. [ShadowingCard](../../src/resources/js/spa/widgets/trainer/ShadowingCard.vue) uploads after recording. Its extension mapping treats non-WAV/non-MP4 as WebM, so Ogg fallback needs correct MIME/extension handling in implementation. Upload validation currently allows WebM/WAV/MP3/M4A/Ogg/MP4 up to 25,600 KB; no recording-duration guarantee comes from that byte limit.

Browser constraints: microphone access needs permission and a secure context ([MDN getUserMedia](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia)); probe formats with [MediaRecorder.isTypeSupported](https://developer.mozilla.org/en-US/docs/Web/API/MediaStream_Recording_API). WebKit documented MP4/AAC support ([MediaRecorder announcement](https://webkit.org/blog/11353/mediarecorder-api/)); this older announcement is not a claim that every current Safari version lacks WebM. Capture and replay capabilities must be tested on actual iOS Safari and Android Chrome, including denial, background/lock, interruptions, empty recordings and rerecord. Ask only for the microphone after a tap; stop tracks and revoke object URLs.

Proposed storage policy:

- Keep a browser-local preview even if analysis fails. Saving is an explicit action independent of provider success, after explaining retention and cloud analysis separately.
- A private owner-scoped recording record stores disk/path, actual MIME/codec, bytes, measured duration, checksum, creation/expiry, pin state and optional attempt ID. Proposed MVP bounds: 30 seconds, 5 MB/upload, 100 MB total private quota per learner, including pinned clips and replay derivatives. Enforce decoded duration and size server-side; reject invalid/disguised media before processing.
- Store compressed original for replay. Derive temporary mono 16 kHz PCM WAV for Azure using existing ffmpeg normalization. Delete processing derivatives promptly; never route retained originals through temporary cleanup. Generate a replay derivative only if cross-device format tests require it, and count it in quota.
- Newly saved unpinned clips expire after 90 days; show the date and permit Pin. At quota, ask to delete/unpin or export; never evict pinned clips or silently apply this policy to existing data. Pinned clips have no automatic expiry until explicit deletion/unpin; unpin must explain the new expiry date. The model distinguishes null expiry for pins from orphaned processing files.
- Authenticated replay/download/delete must verify ownership server-side, support range requests, and avoid public bucket URLs. If using signed URLs, make them short-lived after authorization. Expiry/delete remove originals and derivatives; retain a tombstone and scores so charts do not imply playable files. Define backup expiry in implementation; app deletion does not instantly erase backups.

At an assumed 64 kbit/s, 30 seconds is 240 KB plus overhead, ten minutes is 4.8 MB and twenty such sessions is 96 MB. PCM WAV at 16 kHz mono/16 bit is 320 KB per ten seconds; avoid durable WAV copies. These are size calculations, not guaranteed browser bitrates. Self-video later only if visual delivery/body-language practice becomes a goal.

Privacy: local deletion and vendor retention are separate. Microsoft says pronunciation-assessment customer inputs are not retained by the service ([Speech data privacy](https://learn.microsoft.com/en-us/azure/foundry/responsible-ai/speech-service/speech-to-text/data-privacy-security)). OpenAI publishes endpoint-specific retention, training exclusions and account eligibility for special controls ([API data controls](https://developers.openai.com/api/docs/guides/your-data)); verify the selected audio and text endpoints/account before making a retention promise. Never log audio, full private transcripts or signed URLs. Show which provider receives speech; sending recordings requires consent, not merely microphone permission.

## 4. Feedback: what can be measured

The existing [STT contract](../../src/app/Modules/Learning/Application/Contracts/SpeechToTextProviderInterface.php) and [pronunciation contract](../../src/app/Modules/Learning/Application/Contracts/PronunciationAssessmentProviderInterface.php) are reusable. [OpenAI STT](../../src/app/Modules/Learning/Infrastructure/OpenAiSpeechToTextProvider.php) defaults to `gpt-4o-mini-transcribe`, returning text and null confidence. [Azure adapter](../../src/app/Modules/Learning/Infrastructure/AzurePronunciationAssessmentProvider.php) requests phoneme granularity and comprehensive assessment, returns word JSON and nullable dimensions, and maps plain `en` to `en-US`. It does **not** currently enable prosody. Parsing `ProsodyScore` does not make that metric available.

| Metric | Appropriate use / limitation | MVP |
|---|---|---|
| Azure accuracy, word/phoneme evidence | Scripted acoustic feedback; model-dependent estimate, not human intelligibility ground truth. Low scores can reflect noise or accent mismatch. | Optional per-word retry guidance; inspect actual returned phonemes before asserting a dropped sound. |
| Azure fluency | Provider estimate based on timing/breaks; compare like tasks and version/locale. | Optional separate dimension; never call it conversational fluency. |
| Completeness / recognized text diff | Helps locate skipped/extra words in a scripted take; ASR can repair or misrecognize speech. | Show transcript for correction and label text match separately from pronunciation. |
| Prosody | Stress/intonation/rhythm estimate; opt-in, en-US only, paid add-on. It does not compare directly with the TED speaker's delivery. | Defer default activation; nullable/unavailable until validated and approved. |
| Speaking rate | Words ÷ measured elapsed speech interval; “faster” is not universally better. | Later descriptive measure; exclude silence consistently and record method. |
| Pauses / fillers | Need timestamped speech/VAD; transcript-only STT may omit fillers. Natural rhetorical pauses should not be penalized. | Later; current adapter has no timing/confidence output. |
| Pitch contour overlay | Requires accessible reference audio, voiced F0 extraction, alignment and pitch normalization; different voices and expressive choices can differ legitimately. | Experimental later visualization, never a similarity grade. |

[Azure short-audio REST documentation](https://learn.microsoft.com/en-us/azure/ai-services/speech-service/rest-speech-to-text-short) limits pronunciation assessment to 30 seconds and documents `EnableProsodyAssessment` in the request. Use existing REST for bounded solo takes. [Assessment guidance](https://learn.microsoft.com/en-us/azure/ai-services/speech-service/how-to-pronunciation-assessment) requires continuous processing beyond 30 seconds; continuous mode does not support EnableMiscue and omissions/insertions need a separate text comparison. Prosody is en-US only. One-minute talks therefore need an intentional continuous/unscripted provider capability; simply increasing the recorder timer is insufficient. Other languages/locales must remain explicit unsupported/unavailable states, never silently use English scores.

Existing [ExerciseAttemptService](../../src/app/Modules/Learning/Application/ExerciseAttemptService.php) averages scripted text-comparison score and Azure accuracy, while correctness remains from text comparison. Do not relabel that combined percentage as holistic pronunciation. New coach results must preserve raw dimensions, provider/model/locale and scoring version. Demo/stub results are visibly labeled and excluded from real progress. Failed/unavailable scoring leaves recording/replay usable and does not create failure-based learning penalties.

## 5. AI coaching and session cost

LLM session summary receives structured, validated evidence plus corrected transcript, never unsupported acoustic guesses. Return at most two strengths, one priority tip, the attempt/word evidence and one retry drill. “Final /t/ may need attention” requires repeated phoneme evidence on usable audio; “flat questions” requires prosodic evidence. Without it, suggest a listening exercise as a practice idea. Proposed recurring-error trigger: same issue in at least three valid takes over two sessions; this threshold needs calibration. Useful phrases remain linked to source/lexeme and require Vika's choice to save. Later own-talk vocabulary/grammar suggestions must distinguish ASR uncertainty, meaning preservation and stylistic alternatives from actual errors. Generate topics from the current talk/known phrases; neither free conversation nor realtime avatars is needed now.

### Cost worksheet (USD, checked 2026-10-03)

Define a session as **ten minutes of uploaded learner audio**, regardless of wall-clock practice length, plus one summary with 2,000 input and 500 output text tokens. A five-minute routine may upload much less. Every submitted retry and failed-but-billed provider request counts again.

| Component | Basis | Ten-minute session |
|---|---|---|
| Current `gpt-4o-mini-transcribe` | Official [OpenAI pricing](https://platform.openai.com/pricing): estimated $0.003/audio minute | $0.030 |
| Proposed `gpt-4.1-mini` summary | Official [model pricing](https://developers.openai.com/api/docs/models/gpt-4.1-mini): $0.40/M input and $1.60/M output tokens | `2000 × .40 / 1e6 + 500 × 1.60 / 1e6 = $0.0016` |
| Azure base assessment | Regional/account STT rate `A` USD/audio hour | `A / 6` |
| Optional Azure prosody | Regional add-on rate `P` USD/audio hour | `P / 6` |
| Replay-only mode | No external calls | $0 provider spend; storage/hosting still applies |

[Azure's pricing page](https://azure.microsoft.com/en-us/pricing/details/speech/) returned `$-` placeholders for numerical rates in this research environment; a verified regional quote is unavailable. Microsoft confirms base assessment is charged like standard STT and prosody is additional ([assessment pricing explanation](https://learn.microsoft.com/en-us/azure/ai-services/speech-service/pronunciation-assessment-tool)). Do not present an invented regional price as current.

Total with both providers and summary: **`$0.0316 + (A + P) / 6`**. Purely illustrative sensitivity assumptions `A=$1/hour`, `P=$0.30/hour` yield **$0.2483/session**, or **$4.97 for twenty sessions**; without prosody, $0.1983/session or $3.97/month. These Azure inputs are examples, not verified prices. For three uploaded minutes, the same summary gives `$0.0106 + (A+P)/20`, illustratively $0.0756. Existing code transcribes with OpenAI and then assesses with Azure; avoiding duplicate STT on scripted takes could reduce costs later, but requires outcome parity tests, not an assumption of equivalence.

Retries could triple provider charges; illustrate the ten-minute full scenario at ~**$14.90/month** if all calls are paid three times. Excluded: taxes, FX, storage/egress, ffmpeg/worker hosting and any human coach. Proposed hard limits of **$5/month and $0.25/session including retries** require Vika’s approval; they are not activated settings. Target about three uploaded minutes per routine. The illustrative ten-minute full scenario nearly exhausts the per-session cap, so reject further paid retries if the next reservation exceeds it and offer replay/self-check instead. Budget enforcement must reserve an estimate before a call, reconcile actual usage where available, count retries, and fall back to replay at the cap. Provider limits/free quotas must be checked for the actual account; no free allowance is assumed. No new paid integration has been authorized by this document.

## 6. Progress, confidence and SRS

Start with an audio timeline grouped by phrase and date: choose two takes, hear “Then” and “Now,” rerecord the same prompt, optionally add a personal reflection. Scores are secondary. Later charts show weekly medians, sample counts, task mode, locale/provider/version and a sufficient-data state; never average missing scores as zero. Keep separate scripted and spontaneous lanes, baseline prompts and novel transfer prompts. Changes of microphone/noise/model should be visible context; a small numerical rise alone is not proof of learning.

Before trusting feedback, use a consented pilot with quiet/noisy takes, silence, missing words and varied speakers/accents; independently rate comprehensibility and verify highlighted word/phoneme errors. Evaluate repeatability on the same file, helpfulness of one tip and no unsupported diagnoses. Thresholds and release criteria must be recorded before rollout. A clinician-like acoustic claim cannot be established by a passing API mock test.

Learning owns confidence; SRS owns schedules. Current `recordLearningOutcome` updates speaking confidence and schedules reviews only when an attempt has a content lexeme; a free talk cannot fairly mark every mentioned word learned. Preserve [ADR-010 canonical repetition-card direction](../architecture/adr/ADR-010-word-keyed-repetition-and-personal-lexemes.md): use the canonical lexeme, actual source encounter and bounded evidence, not a new phrase-specific parallel scheduler. Replay/saving, charts and uncertain/provider-failed takes cause no progress mutation. Coach mode should not copy the current 90% text/combined-score threshold as a new mastery rule. Explicit established lexeme practice can feed existing confidence once idempotently after policy validation; whole-talk grammar/phrases are suggestions only. The learned marker stays a learner decision.

## 7. Architecture sketch and delivery

Reuse `exercise_attempts` as the assessment audit boundary, with a small proposed `speaking_sessions` owner/context record and private `speaking_recordings` linked optionally to an attempt. A session stores user, language, content, mode, selected source segment IDs, target/timing snapshot, practice-policy version and timestamps. A recording stores retention metadata described above. Structured assessment evidence stays on the attempt; store session summary and provider metadata separately from scheduler state. This is a data-model proposal requiring the follow-up implementation scope, not a migration delivered by research.

| Module | Responsibility |
|---|---|
| Content | Accepted transcript access and deterministic suitable-phrase selection through the existing exercise-content boundary; source content stays authoritative. |
| Learning | Session, recording ownership/retention, playback authorization, attempt lifecycle, comparison timeline and confidence policy. |
| AI | Speech/coaching capabilities behind existing provider contracts; adapter-specific formats, routing, usage, errors and evidence-based summary generation. Follow [ADR-008](../architecture/adr/ADR-008-ai-platform-boundary.md); no vendor client in product services. |
| SRS | Existing canonical review history and schedule; no automatic whole-talk schedule. |
| Infrastructure | Private disk/object storage, ffmpeg, queues, cleanup and redacted tracing. Redis is transient coordination; Postgres is durable state. |

Current [attempt lifecycle documentation](../architecture/exercise-attempts.md) and [cleanup command](../../src/app/Console/Commands/CleanupExerciseAudioCommand.php) delete temporary audio after processing/expiry. New retained recording paths must be separate so failed/retried analysis and hourly orphan cleanup cannot erase saved originals. Upload → validate/store recording → persist attempt → dispatch processing job → poll existing lifecycle → optional summary after completion. Pass IDs through jobs, authorize before reading media, use attempt/session idempotency keys and unique outcome markers. Call simple Content contracts directly; use queues for decoding/provider analysis. Events are justified for a completed assessment's secondary effects, not for every button tap.

### Three implementation tickets and one approval gate

| Ticket scope | Dependencies | Acceptance / focused verification |
|---|---|---|
| **[VIK-50: Private recordings, replay and comparison baseline](https://linear.app/viktoryia/issue/VIK-50/speaking-coach-private-audio-replay-and-then-vs-now-recordings)** | Blocked by VIK-45; no paid service gate | Explicit opt-in Save; private download/replay/delete; measured 30s/5MB bounds; visible 90d expiry and pin quotas; preserve audio on provider failure; same-phrase Then/Now timeline. Test owner isolation/range replay, invalid media, independent temporary cleanup, expiry/pins/quota and no progress changes. Real iOS/Android capture/replay/permission checks at 360/390px. |
| **[VIK-51: Bounded TED practice in Easy/Medium/Hard](https://linear.app/viktoryia/issue/VIK-51/speaking-coach-short-ted-phrase-practice-with-continue-and-loop-replay)** | Blocked by VIK-45, VIK-50 and VIK-29; VIK-15 related only | Accepted timed segments only; five deterministic 5–20s phrases, invalid-boundary fallback, supported slow rate/loop, stopped playback for recording; Hard short retell with self-replay and no target-text grading; Continue and one-next-action flow; works with all AI disabled. Test fragment selection and rate-aware looping; mobile/no-scroll and embedding-unavailable checks. |
| **[VIK-52: Optional trustworthy feedback, summary and comparable trends](https://linear.app/viktoryia/issue/VIK-52/speaking-coach-optional-grounded-feedback-and-weekly-speaking-trends)** | Blocked by VIK-45, VIK-49 and VIK-50; usable on recordings independently of VIK-51 | Feature disabled until configured/approved; short-take Azure/OpenAI adapters, nullable dimensions, evidence-linked tips, demo exclusion, usage/cap/retry accounting, provider failure preserves replay; weekly like-for-like dimensions. Test contract parsing, malformed/unsupported results, duplicate jobs/outcomes and cap. Consented real-provider pilot verifies credibility; do not release prosody solely on mocks. |
| **[VIK-49: Approve cloud-analysis budget/region](https://linear.app/viktoryia/issue/VIK-49/speaking-coach-approve-paid-feedback-budget-and-provider-scope)** | Review this document; blocks MVP 3 only | Vika approves providers, regional quote, consent/data handling and monthly limit; recommended $5/month and $0.25/session, retries included, with replay fallback, prosody off initially. No implementation until this is answered. |

MVP implementation tickets can carry `ready-for-agent` while retaining explicit blockers; the budget ticket is `ready-for-human`. Research has no code to run on phone; Vika can review this doc now, then try MVP 1 by recording the same phrase twice and comparing takes. The links above identify the created implementation and approval tickets; native Linear blockers mirror this table.

Roadmap, in order: (1) assessed free retelling and one-minute talks with continuous/unscripted assessment capability and appropriate semantic feedback; (2) repeated-mistake minimal-pair drills and phrase reuse in existing lexeme practice; (3) opt-in en-US prosody after calibrated evaluation; (4) experimental aligned pitch visualization with licensed/access-controlled reference audio; (5) optional self-video/body-language coaching only with an explicit goal. These are directions, not extra tickets required to deliver the three-ticket MVP.

### Remaining uncertainty and research verification

All seven requested topics are covered, including sourced learning/app/provider evidence and auditable cost arithmetic. Repository paths/contracts were read directly; pricing and provider constraints were checked in primary documentation. This research makes no application code, schema, runtime or provider-account changes. Real-phone capture, audio quality, cloud credentials, regional pricing, pilot validity and implementation performance are unverified and have explicit follow-up checks. Retained audio cannot be recovered from previously deleted attempts. Before implementation, rebase and recheck existing adapters/ADR-010 integration because parallel tickets may evolve those foundations.
