# Admin Pipeline Plan

## Goal

Admin should be able to operate the full content pipeline from one backoffice flow:
accept a submitted YouTube link, fetch transcript, run AI analysis agents, review extracted learning material, correct mistakes, and publish the content when it is ready.

This plan assumes the first version does not use audio transcription. The system works with YouTube subtitles/transcript only, plus a manual transcript fallback.

## What Admin Sees

### Content Queue

Add an admin queue view for content grouped by operational status:

- `pending`: accepted and waiting for pipeline.
- `processing`: pipeline is running.
- `needs_manual_transcript`: no transcript was found; admin can paste transcript.
- `analysis_ready`: AI extraction finished and awaits review.
- `ready`: published to catalog.
- `failed`: technical failure; admin can retry.
- `rejected`: intentionally rejected.

The queue should show:

- title;
- source URL;
- source language;
- target language;
- submitted by;
- status;
- current pipeline step;
- last failure reason;
- created date;
- action buttons.

### Content Pipeline Detail

On each content detail page, admin should see a pipeline panel:

- transcript status;
- AI lexeme extraction status;
- phrase/phrasal verb extraction status;
- CEFR classification status;
- grammar extraction status;
- validation/deduplication status;
- review status;
- publication status.

Each step should show:

- status: `not_started`, `running`, `completed`, `failed`;
- started/completed timestamps;
- provider/model used;
- failure reason;
- retry button where safe.

### Transcript Workspace

Admin should be able to:

- fetch transcript from YouTube;
- view the raw transcript;
- view normalized transcript;
- manually paste transcript when automatic fetch is unavailable;
- choose transcript language if several are available;
- mark transcript as accepted;
- rerun downstream analysis after transcript edits.

Important rule: editing transcript should invalidate old AI extraction results or mark them stale.

### AI Analysis Workspace

Admin should be able to run separate analysis steps:

- extract words;
- extract phrases, phrasal verbs, idioms, collocations;
- classify CEFR level;
- extract grammar constructions;
- generate short explanations/examples;
- validate and deduplicate results.

Each step should be independently runnable and retryable. This keeps the flow understandable and makes the multi-agent design visible without needing a separate orchestration product.

### Review Extracted Lexemes

Admin should review a table of extracted learning units:

- text;
- normalized form;
- type: `word`, `phrase`, `phrasal_verb`, `idiom`, `collocation`;
- source language;
- target language;
- CEFR level;
- confidence;
- translation or explanation;
- example from transcript;
- transcript fragment or timestamp if available;
- status: `draft`, `approved`, `rejected`, `edited`.

Admin actions:

- approve selected;
- reject selected;
- edit text/type/level/explanation;
- merge duplicates;
- add missing lexeme manually;
- bulk approve high-confidence items;
- filter by level, type, confidence, and status.

### Review Grammar Constructions

Admin should review grammar items separately from lexemes:

- construction name;
- category;
- CEFR level;
- explanation;
- examples from transcript;
- related lexemes;
- confidence;
- status.

Examples:

- Present Perfect;
- first conditional;
- modal verbs for advice;
- passive voice;
- phrasal verb patterns;
- comparative constructions.

Admin actions:

- approve/reject/edit;
- link to existing grammar catalog rules;
- create a new grammar rule if no match exists;
- attach examples from the transcript.

### Publish Controls

Admin can publish only when minimum readiness criteria pass:

- transcript accepted;
- at least one approved lexeme or grammar item exists;
- AI analysis has no blocking failure;
- content metadata is complete: title, source language, target language, type, level.

Publishing changes content visibility to user catalog.

## Backend Needs

### Status Model

Extend the current ingestion status model with review-oriented statuses or secondary fields:

- `pipeline_step`;
- `pipeline_state`;
- `review_status`;
- `analysis_version`;
- `last_analyzed_at`;
- `analysis_failure_reason`.

Avoid overloading `Content.status` with every sub-step. `Content.status` should stay product-visible; detailed operational state can live in pipeline tables.

### Pipeline Runs

Add persistent pipeline run records:

- `content_pipeline_runs`;
- `content_pipeline_steps`;
- `ai_analysis_runs`.

These records give admin visibility and make retries auditable.

### Draft Analysis Tables

AI output should first be saved as draft, not immediately published:

- `content_lexeme_candidates`;
- `content_grammar_candidates`;

After approval, candidates become canonical records:

- `lexemes`;
- `content_lexeme_links`;
- `grammar_rules`;
- `content_rule_links`.

### AI Agent Roles

Implement role-specific services behind the AI module:

- `TranscriptNormalizerAgent`;
- `LexemeExtractorAgent`;
- `PhraseExtractorAgent`;
- `LevelClassifierAgent`;
- `GrammarExtractorAgent`;
- `AnalysisValidatorAgent`.

These can be services/classes first. They do not need separate processes initially.

### AI Provider Abstraction

Use a provider-neutral interface:

- `AiChatClient`;
- `AiJsonClient`;
- `AiEmbeddingClient` if needed later.

Adapters:

- Claude direct API;
- OpenRouter API;
- optional OpenAI/Ollama later.

Config should select provider/model without changing pipeline code.

## Implementation Stages

### Stage 1: Pipeline Visibility

- Add pipeline state fields or tables.
- Show pipeline status panel in Filament content detail.
- Add retry buttons for existing transcript/process jobs.
- Surface failure reasons clearly.

### Stage 2: Transcript Workspace

- Add admin transcript view/edit form.
- Add manual transcript fallback.
- Add "accept transcript" action.
- Mark downstream analysis stale when transcript changes.

### Stage 3: AI Candidate Extraction

- Add AI JSON extraction contract.
- Save lexeme and grammar candidates as drafts.
- Store confidence, provider, model, prompt version, and raw response.
- Add tests for valid/invalid AI JSON.

### Stage 4: Admin Review

- Add candidate review tables in Filament.
- Add approve/reject/edit/merge actions.
- Add bulk operations.
- Publish approved candidates into canonical learning tables.

### Stage 5: Publish Gate

- Add readiness checks.
- Block publishing when required data is missing.
- Add clear admin messages explaining what is missing.

## Risks

- AI output quality will vary by language and transcript quality.
- CEFR classification may be subjective; store confidence and allow admin override.
- Transcript timestamps may not always be available.
- Running every AI step separately can cost more; add per-step retry and caching.

## First Useful Milestone

Admin can open one submitted YouTube content, fetch or paste transcript, run lexeme extraction, review candidates, approve selected items, and publish the content to the user catalog.
