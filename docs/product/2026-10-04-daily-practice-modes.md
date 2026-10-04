# Daily practice: default modes for Today

Research for [VIK-13](https://linear.app/viktoryia/issue/VIK-13/research-which-practice-modes-belong-in-the-daily-flow).
Consumers: VIK-28 (Today), VIK-30 (start-learning implementation), VIK-32
(hint → retry → answer). This document **confirms and sharpens** the provisional
Medium × Mixed sequence in the [start-learning design](2026-10-03-start-learning-design.md)
(VIK-29); it does not replace its launcher, mapping or mockups. Current
behaviour is in the [inventory](2026-10-03-start-learning-inventory.md); none of
the sequence below is shipped yet.

## Answer

Today runs the learner's saved challenge (default **Medium × Mixed**, 20
activities, ~15 min) over due reviews first, then new words.

**New word** (unchanged from VIK-29, now evidence-checked):

| # | Step | Format | Scored | Why it is here |
|---|---|---|---|---|
| 1 | Introduce | Source sentence + meaning, hear it, `Ready` | no | A first exposure the later retrievals can recall from |
| 2 | Recognize | Choose the target in its sentence | yes | Low-error first retrieval right after study [B07] |
| 3 | Recall | Meaning + context → **type** the word | yes | Core step: effortful, objective form recall [K08, KMR07, W09] |
| 4 | Listen | Hear word/phrase → choose its meaning | yes | Receptive, spoken route; product goal is real video/audio |
| 5 | Use | Type the word into a saved sentence gap | yes | Form in context; second productive retrieval [KW23] |

**Review** (unchanged from VIK-29, now evidence-checked):

1. **Recall first**: meaning + context → type the word, no answer visible.
2. Only if a skill is weak: one task for the weakest skill (production → typed
   sentence gap, listening → hear/choose, recognition → choose). A strong
   word ends after step 1.
3. Failed recall → feedback (VIK-32), then assisted recognition; unassisted
   recall returns after ≥ 3 other activities if the round has room, otherwise
   it stays a due retry for the next session.

**Out of the main path** (reachable through Change → Focus/Challenge or
Advanced → More practice): reveal & self-grade, tap letters, choose-the-word as
a standalone drill, dictation, speaking/shadowing, context self-check, read &
reveal, write flexible/exact, build the sentence, transcript exercises, ready
check. Full table in § Modes outside the daily path.

## Evidence

The audit sources ([A2 baseline](improvement-audits/2026-09-15-a2-baseline.md),
§ Research comparison) were rechecked and extended with primary studies. Every
citation below was resolved by DOI on 2026-10-04.

| Finding | Source | Consequence for Today |
|---|---|---|
| Repeated **retrieval** after first success raised delayed recall of foreign-language words; repeated restudy did not. Learners' predictions were uncorrelated with their results. | Karpicke & Roediger 2008 [K08] | Every new word must reach an unassisted retrieval in the same session; the unscored introduction is not learning by itself. |
| A pause allowing the learner to retrieve an L2 word before seeing it beat plain viewing at 2 days and 1 week. | Barcroft 2007 [B07] | Retrieval steps start immediately after the introduction. |
| With corrective feedback, a short-answer test beat a multiple-choice test on a 3-day final test; without feedback, MC did better. | Kang, McDermott & Roediger 2007 [KMR07] | Typed answers are the core; choice is a scaffold. Typed steps require feedback (VIK-32). |
| Feedback after MC raises later correct recall and reduces intrusions of wrong options. Feedback after errors is what makes wrong answers useful. | Butler & Roediger 2008 [BR08]; Pashler et al. 2005 [P05] | Recognize/Listen choices keep immediate feedback; no exercise ends on a silent wrong answer. |
| Productive learning of word pairs (meaning → form) gave larger gains on most receptive and productive measures; receptive learning only won for receptive meaning. L1→L2 presentation was the more versatile order. | Webb 2009 [W09]; Griffin & Harley 1996 [GH96] | Recall = type the word from its meaning. The receptive direction is still covered by Recognize and Listen. |
| Five or seven within-session retrievals beat one or three; per minute spent, one retrieval was most efficient. For concepts, three initial correct recalls followed by spaced relearning gave durable and efficient learning; relearning dominated. | Nakata 2017 [N17]; Rawson & Dunlosky 2011 [RD11] | Keep the cap of four scored exercises per new word; put further gains into later spaced reviews, not more same-day drills. |
| Spacing within a session (one large stack instead of small massed stacks) beat massing for 90% of learners, though 72% believed the opposite. Part vs whole set size mattered little once spacing was equal. | Kornell 2009 [K09]; Nakata & Webb 2016 [NW16] | Interleave other words between steps (≥ 3 activities, as VIK-29). Do not let learners' feeling of fluency remove the interleaving. |
| Spacing has a medium-to-large effect on L2 learning; longer gaps win at delay; equal and expanding schedules were equivalent. | Kim & Webb 2022 [KW22] | Retention comes from SRS reviews across days; Today's job is to make those reviews happen. |
| Fill-in-the-blank and flashcards produced no significant overall difference at two weeks when both were spaced. | Kim & Webb 2023 [KW23] | The sentence gap is valuable as context and variety, not as a stronger mode; missing examples may fall back to recall without loss. |
| Learners judge learning while the answer is visible and overestimate later recall. | Koriat & Bjork 2005 [KB05]; also [K08] | Reveal & self-grade is not a default step where an objective check exists. |
| An unsuccessful retrieval attempt followed by the answer improves later learning. | Kornell, Hays & Bjork 2009 [KHB09] | Review starts with recall even when failure is likely; no re-introduction before the attempt. |
| Practice testing and distributed practice are the two "high utility" techniques; interleaving is "moderate". | Dunlosky et al. 2013 [D13]; Carpenter, Pan & Butler 2022 [CPB22] | The default path is built from retrieval + spacing; other modes are optional. |
| On delayed tests, word-focused activities kept ~39% meaning-recall but only ~25% form-recall gains; direction of learning was a moderator. | Webb, Yanagisawa & Uchihara 2020 [WYU20] | Form recall decays fastest, so it is the anchor in both new-word and review sequences. |

**Limits.** The studies use word pairs, students and short lists; none tests
this app or adult learners using video sources. The Listen step is a
**product** choice (Vika learns from YouTube and wants to understand speech),
not one these sources prove. Speaking goals are served by the Speaking focus
and VIK-45, not by this evidence. Validation in the app is a follow-up (§ Next).

### Reference UX

The Clozemaster screenshots (`example.jpg`, `photo_2026-08-13_*.jpg`) show the
same principle: one card type (a sentence with a gap, translation and audio) for
every session; difficulty changes the **answer format** (choice 1× / typing 2×),
not the exercise list; next review is shown after each answer; all audio and
translation options live in settings. Today keeps that shape: a few formats
chosen by the coordinator, with modes reached from Change, not from the main path.

## Rules for the Today coordinator

These fill gaps VIK-29 left for a daily session. They are design rules for
VIK-28/VIK-30, not a new API or schema.

1. **Order.** Due retries → due reviews → new words → optional grammar round
   (VIK-28, VIK-47). Reviews never wait behind new words.
2. **Start a new word only if the round can still reach its Recall step**
   (Recognize + Recall, with the ≥ 3-activity spacing relaxed only for small
   pools). Introducing a word without a retrieval is study without the retrieval
   effect [K08]. Steps 4–5 may continue in the next session (VIK-29).
3. **New-word count** = min(daily new-word cap, what the remaining activities
   allow at four scored steps each). Example at 20 activities: no reviews →
   about 5 new words; 10 strong reviews → about 2 new words; 20+ due reviews →
   no new words today, and the Done screen says so.
4. **Backlog.** When due reviews exceed the round, Today shows only reviews,
   most overdue first. More sessions are offered on Done (`Practice more`),
   never a longer compulsory round.
5. **Failed step of a new word** → VIK-32 feedback, then the same step once more
   after ≥ 3 other activities. A retry uses one of the four scored slots; when
   the slots run out the word continues next session.
6. **Recall requires a meaning cue.** If a lexeme has no translation, meaning
   or saved context, recall becomes a labelled reveal & self-grade (the only
   place self-grade appears by default), never a visible-target typing task.
7. **One scheduling outcome per word per session** (VIK-29). Skill steps update
   confidence; the SRS card advances once, from the recall result.
8. **Challenge stays honest.** Easy and Hard change formats as in the VIK-29
   matrix; Today does not add More practice modes to Easy or remove Recall from Hard.

## Modes outside the daily path

These modes are not used by the default Medium × Mixed Today path. Nothing is
deleted; homes follow the [VIK-29 mapping](2026-10-03-start-learning-design.md#complete-mapping).

| Mode | Where it lives | Why not in the daily default |
|---|---|---|
| Reveal & self-grade | Easy × Words; rule 6 fallback | Self-judgement with the answer visible overestimates recall [KB05] |
| Tap letters | Easy × Words; hint aid | Assisted recall; less effortful than typing [KMR07] |
| Choose the word (standalone drill) | Easy × Words | Used inside Medium as step 2 only; MC alone is the weaker format [KMR07] |
| Quick-check (word → translation) | Easy × Words | Recognition only; covered by step 2 |
| Listening cards / listen-recognize (self-report) | Easy × Listening | Today's Listen step uses an objective choice instead of "Knew it / Didn't know" |
| Dictation | Hard × Listening, Hard × Mixed | High effort; needs audio; better for learners who chose listening |
| Speaking / shadowing | Speaking focus; More practice (transcript) | Needs microphone/providers; speaking progress belongs to VIK-45 |
| Context self-check | Hard × Words | Self-graded sentence production; step 5 gives an objective gap instead |
| Read & reveal | More practice | No retrieval and no saved outcome |
| Write flexible / exact | More practice | AI-graded sentence translation; no saved SRS outcome today |
| Build the sentence | More practice | Order recognition; no saved outcome |
| Transcript exercises | More practice | Bound to one source with a timed transcript |
| Ready check | More practice | A content exam, not daily practice |
| Adaptive (as a separate mode) | Medium × Mixed itself | Becomes the coordinator, not a choice |

## Decisions recorded

`assumed`, PO proxy, logged in [DECISIONS.md](../../.agents/skills/product-owner/DECISIONS.md):
VIK-29's Medium sequences are confirmed for Today; rules 1–8 above are the
daily defaults; Listen stays in the default as a product choice. No Escalate
item: no data change, no direction change.

## Next

- VIK-30: the Listen step needs an objective hear → choose-meaning exercise
  (today's listen-recognize is self-report); recall needs rule 6's cue check.
- VIK-28: apply rules 1–5 to the Today plan and Done screen.
- Follow-up research ticket: measure next-review recall success by step and
  challenge from existing attempts and metric events, to check this default in
  the app instead of relying only on lab studies.

## Sources

- [B07] Barcroft, J. (2007). Effects of opportunities for word retrieval during second language vocabulary learning. *Language Learning*, 57(1), 35–56. https://doi.org/10.1111/j.1467-9922.2007.00398.x
- [BR08] Butler, A. C., & Roediger, H. L. (2008). Feedback enhances the positive effects and reduces the negative effects of multiple-choice testing. *Memory & Cognition*, 36(3), 604–616. https://doi.org/10.3758/MC.36.3.604
- [CPB22] Carpenter, S. K., Pan, S. C., & Butler, A. C. (2022). The science of effective learning with spacing and retrieval practice. *Nature Reviews Psychology*, 1(9), 496–511. https://doi.org/10.1038/s44159-022-00089-1
- [D13] Dunlosky, J., Rawson, K. A., Marsh, E. J., Nathan, M. J., & Willingham, D. T. (2013). Improving students' learning with effective learning techniques. *Psychological Science in the Public Interest*, 14(1), 4–58. https://doi.org/10.1177/1529100612453266
- [GH96] Griffin, G., & Harley, T. A. (1996). List learning of second language vocabulary. *Applied Psycholinguistics*, 17(4), 443–460. https://doi.org/10.1017/S0142716400008195
- [K08] Karpicke, J. D., & Roediger, H. L. (2008). The critical importance of retrieval for learning. *Science*, 319(5865), 966–968. https://doi.org/10.1126/science.1152408
- [K09] Kornell, N. (2009). Optimising learning using flashcards: Spacing is more effective than cramming. *Applied Cognitive Psychology*, 23(9), 1297–1317. https://doi.org/10.1002/acp.1537
- [KB05] Koriat, A., & Bjork, R. A. (2005). Illusions of competence in monitoring one's knowledge during study. *JEP: Learning, Memory, and Cognition*, 31(2), 187–194. https://doi.org/10.1037/0278-7393.31.2.187
- [KHB09] Kornell, N., Hays, M. J., & Bjork, R. A. (2009). Unsuccessful retrieval attempts enhance subsequent learning. *JEP: Learning, Memory, and Cognition*, 35(4), 989–998. https://doi.org/10.1037/a0015729
- [KMR07] Kang, S. H. K., McDermott, K. B., & Roediger, H. L. (2007). Test format and corrective feedback modify the effect of testing on long-term retention. *European Journal of Cognitive Psychology*, 19(4–5), 528–558. https://doi.org/10.1080/09541440601056620
- [KW22] Kim, S. K., & Webb, S. (2022). The effects of spaced practice on second language learning: A meta-analysis. *Language Learning*, 72(1), 269–319. https://doi.org/10.1111/lang.12479
- [KW23] Kim, S. K., & Webb, S. (2023). Does spaced practice have the same effects on different second language vocabulary learning activities? Fill-in-the-blanks versus flashcards. *The Modern Language Journal*, 107(4), 944–964. https://doi.org/10.1111/modl.12879
- [N17] Nakata, T. (2017). Does repeated practice make perfect? The effects of within-session repeated retrieval on second language vocabulary learning. *Studies in Second Language Acquisition*, 39(4), 653–679. https://doi.org/10.1017/S0272263116000280
- [NW16] Nakata, T., & Webb, S. (2016). Does studying vocabulary in smaller sets increase learning? *Studies in Second Language Acquisition*, 38(3), 523–552. https://doi.org/10.1017/S0272263115000236
- [P05] Pashler, H., Cepeda, N. J., Wixted, J. T., & Rohrer, D. (2005). When does feedback facilitate learning of words? *JEP: Learning, Memory, and Cognition*, 31(1), 3–8. https://doi.org/10.1037/0278-7393.31.1.3
- [RD11] Rawson, K. A., & Dunlosky, J. (2011). Optimizing schedules of retrieval practice for durable and efficient learning: How much is enough? *Journal of Experimental Psychology: General*, 140(3), 283–302. https://doi.org/10.1037/a0023956
- [W09] Webb, S. (2009). The effects of receptive and productive learning of word pairs on vocabulary knowledge. *RELC Journal*, 40(3), 360–376. https://doi.org/10.1177/0033688209343854
- [WYU20] Webb, S., Yanagisawa, A., & Uchihara, T. (2020). How effective are intentional vocabulary-learning activities? A meta-analysis. *The Modern Language Journal*, 104(4), 715–738. https://doi.org/10.1111/modl.12671
