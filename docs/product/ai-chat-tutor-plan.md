# AI Chat Tutor Plan

## Goal

AI chat should become a learning tutor, not a generic chatbot. It should understand the current user, selected content, chosen lexemes, known words, weak areas, grammar topics, and progress history.

The first version should use a provider-neutral AI layer with Claude and OpenRouter as preferred providers.

## Chat Modes

### General Tutor

User can ask language-learning questions:

- explain a word;
- compare similar words;
- create examples;
- quiz me;
- explain grammar;
- suggest what to study next.

### Content-Aware Tutor

From a content detail or study page, user can open chat with context:

- current content;
- transcript excerpts;
- selected lexemes;
- grammar constructions;
- examples from the video;
- user's known/unknown states.

This lets the tutor answer:

- "Explain this phrase from the video";
- "Give me 5 examples using these words";
- "Quiz me on the phrasal verbs from this video";
- "What grammar should I notice here?"

### Review Coach

From repetitions or progress, user can ask:

- "Why do I keep missing these words?";
- "Make a short drill for my weak words";
- "Give me a memory trick";
- "Create a daily plan for this week".

## AI Agent Roles

Start as role-specific services inside the Laravel app. Do not build external orchestration first.

### TutorAgent

Handles direct conversation with the learner.

Needs:

- user profile;
- language direction;
- selected content context;
- known/learning/weak words;
- recent mistakes;
- preferred explanation language.

### MemoryAgent

Builds and updates compact learner memory.

Stores:

- preferred target language;
- current source languages;
- known words;
- weak words;
- recurring grammar issues;
- learning goals;
- style preferences.

### ExerciseGeneratorAgent

Creates practice tasks:

- fill in the blank;
- multiple choice;
- translation prompt;
- sentence rewrite;
- grammar drill;
- mini-dialogue.

### ReviewPlannerAgent

Suggests what to review next based on:

- SRS due cards;
- weak words;
- recently selected content;
- daily goal;
- time available.

### ContentDiscussionAgent

Specialized role for discussing one content item:

- uses transcript excerpts;
- explains phrases in context;
- asks comprehension questions;
- connects lexemes to grammar constructions.

## Provider Architecture

Use provider-neutral contracts:

- `AiChatClient`: normal chat completion.
- `AiJsonClient`: structured JSON responses for exercises/plans.
- `AiStreamingClient`: optional later for streaming UI.

Preferred providers:

- Claude direct API for high-quality tutoring.
- OpenRouter for provider/model flexibility.

Config examples:

- `AI_PROVIDER=claude`
- `AI_PROVIDER=openrouter`
- `AI_MODEL=claude-sonnet-...`
- `AI_JSON_MODEL=...`

Application services should not depend on vendor-specific SDK details.

## Chat Context

Every chat request should build a bounded context object:

- user identity;
- source language;
- target/explanation language;
- current page context;
- selected content;
- selected lexemes;
- weak lexemes;
- known lexemes;
- grammar topics;
- recent answers/mistakes;
- conversation history summary.

Avoid sending unlimited raw history. Use summaries and selected excerpts.

## Memory Model

Add learner memory as structured data, not only chat logs.

Possible records:

- `learner_memory_profiles`;
- `learner_memory_items`;
- `ai_conversation_summaries`;
- `ai_tutor_recommendations`.

Memory item examples:

- `struggles_with`: "phrasal verbs with get";
- `knows`: "basic food vocabulary";
- `prefers_explanations_in`: "Russian";
- `goal`: "understand YouTube videos in English";
- `avoid`: "too much grammar terminology".

## Chat UI

### Basic Chat Page

Show:

- conversation list or current conversation;
- messages;
- input;
- quick actions:
  - explain my weak words;
  - quiz me;
  - plan today's study;
  - discuss current content.

### Content Chat Panel

From content detail/study page:

- open side panel or page route;
- show "Chat about this content";
- include selected words and grammar automatically;
- offer quick prompts:
  - explain selected words;
  - quiz me from this video;
  - explain grammar in this transcript;
  - make examples.

### AI Output Actions

AI responses should sometimes create app actions:

- add generated exercise;
- add word to learning list;
- mark as known;
- create review session;
- save explanation.

In the first version, these can be buttons rendered from structured response metadata.

## Safety And Quality

The tutor should:

- say when it is unsure;
- avoid inventing transcript content;
- use only approved content context for content-specific answers;
- keep explanations appropriate to learner level;
- not overwrite user progress without explicit action.

## Implementation Stages

### Stage 1: Provider Abstraction

- Add Claude/OpenRouter adapters.
- Add chat and JSON contracts.
- Add config-driven provider/model selection.
- Keep existing OpenAI/Ollama clients compatible or behind the same layer.

### Stage 2: Context Builder

- Build `TutorContextService`.
- Include user profile, progress, selected words, weak words, current content.
- Add tests for context size and included fields.

### Stage 3: Content-Aware Chat

- Connect chat page to content detail/study routes.
- Pass content ID and selected lexemes.
- Add quick prompts.

### Stage 4: Memory

- Add conversation summary.
- Add structured learner memory.
- Update memory after important sessions, not after every message.

### Stage 5: Exercise Generation

- Add AI-generated drills as structured JSON.
- Let user answer generated exercises.
- Save results into learning/progress where appropriate.

### Stage 6: Review Planner

- Use SRS, weak words, and daily goal to generate a daily plan.
- Show plan on dashboard.

## Risks

- Generic chat can become disconnected from learning. Always build context from app data.
- Unlimited transcript/history can become expensive. Use excerpts and summaries.
- AI-generated exercises need validation before saving progress.
- Provider APIs differ. Keep vendor-specific logic inside adapters.

## First Useful Milestone

User opens a ready content item, selects words, clicks "Discuss with AI", and the tutor can explain selected words and grammar using that content context.
