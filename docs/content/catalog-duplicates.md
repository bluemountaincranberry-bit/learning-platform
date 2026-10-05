# Catalog duplicates (VIK-16)

The shared catalog keeps one row per grammar rule and one row per source video.

## Identity

| Item | Same item when | Stored as |
|---|---|---|
| Grammar rule | Same language, and the title matches after case, spacing and trailing punctuation are ignored | `grammar_rules.normalized_title` (`GrammarRuleTitle`) |
| Content | Same YouTube video id, whatever URL form was pasted (`youtu.be/ID?si=…`, `watch?v=ID&list=…`, `/shorts/ID`) | `contents.source_key` = `youtube:<id>` (`ContentSourceKey`) |

Model `saving` hooks derive both keys. Nothing sets them by hand.

## Prevention

- **AI analysis** (`CandidateMatchingService`) links a grammar candidate to
  the live rule with the same title before it tries embeddings. Lesson
  analysis does the same. Embeddings still catch rules with different
  titles at a cosine similarity of 0.85 or more.
- **Apply** (`AcceptedCandidateWriter`) checks the title again before it
  creates a rule. This covers two runs that were matched before either one
  was applied.
- **Submission** (SPA submit, extension import, admin create) returns 422
  "This video is already in the catalog" for a known video, and also for a
  YouTube link that is not a single video.

Root causes of the incident:

- Grammar matching compared only embeddings of `title + summary`. When the
  AI wrote a new summary, "Passive Voice" scored 0.758 against the existing
  "Passive Voice" rule, which is below the 0.85 threshold.
- Submission checked uniqueness on the raw URL string, per submitter.

## Merging existing duplicates

```bash
php artisan catalog:merge-duplicates                 # dry run: runs every step in a rolled-back transaction
php artisan catalog:merge-duplicates --apply         # merge
php artisan catalog:merge-duplicates --apply --rule=14:26   # also merge near-duplicate #26 into #14
```

**Rules.** The command keeps a published rule before a draft, then the
oldest one. It moves content links, examples, learners' example hides,
exercises, attempts, candidates and learners' progress to the kept rule.
When a learner has progress on both rules, the two rows become one: learned
wins, and the earliest dates are kept. An identical example sentence is kept
once. The duplicate rule is **archived** and its embedding is dropped. Before
archiving, the command reads the database's foreign keys. If any row still
points at the duplicate, the pair rolls back and is reported. Modules join
the merge through `GrammarRuleMergeParticipant`.

**Contents.** The command keeps the copy that learners used, or the oldest
copy when nobody used one. It **rejects** the other copy with the note
"Duplicate of #N". Learner data is found by walking foreign keys from the
content. If more than one copy has learner data, the group is reported for
a manual merge.

The command is idempotent and deletes no content or rule. Take a database
backup first (`docs/operations/backup-restore-runbook.md`). The command
changes learners' progress rows, so a human runs it. Agents do not run it.

The identity columns are indexed but not unique yet. Making them unique
needs a migration after the merge has run on every environment.
