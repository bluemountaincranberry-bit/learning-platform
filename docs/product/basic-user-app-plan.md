# Basic User App Plan

## Goal

The user app should guide a learner from a piece of content to a personal learning plan:
open content, inspect extracted words and grammar, choose what to learn, study selected material, review it later, and track progress.

The first version should be simple and reliable. It should not expose internal pipeline complexity to the learner.

## Primary User Flow

1. User opens the app.
2. User sees available content by language, level, and category.
3. User opens a content item.
4. User sees a short overview:
   - title;
   - source language;
   - target language;
   - level;
   - estimated learning time;
   - number of words/phrases;
   - grammar constructions found.
5. User chooses learning units:
   - I want to learn this;
   - I already know this;
   - skip for now;
   - not interested.
6. User starts a study session.
7. User answers exercises or self-checks.
8. App saves progress and creates SRS cards for selected items.
9. User returns later to repetitions.
10. User can ask AI tutor about this content and selected words.

## Screens

### Home / Dashboard

Replace the current basic landing experience with an app dashboard:

- continue learning;
- due repetitions;
- recommended content;
- daily goal;
- current streak;
- recently added content;
- AI tutor shortcut.

For unauthenticated users:

- show login/register entry;
- show public catalog preview.

### Catalog

Improve catalog filters:

- source language;
- target language;
- level;
- content type;
- topic/category;
- has transcript;
- has grammar;
- search by title.

Content cards should show:

- title;
- language direction;
- CEFR level;
- content type;
- progress;
- number of learnable units;
- readiness state if submitted by current user.

### Content Detail

Content detail should show:

- content metadata;
- transcript preview if available;
- lexeme summary by type and level;
- grammar summary;
- progress for current user;
- actions:
  - choose words;
  - start study;
  - self-check;
  - discuss with AI;
  - add to plan.

### Choose What To Learn

This is the key missing screen.

Show extracted units grouped by:

- words;
- phrases;
- phrasal verbs;
- idioms;
- collocations;
- grammar patterns.

Each item should show:

- text;
- type;
- level;
- short translation/explanation;
- example from content;
- selection state.

User actions:

- learn;
- already know;
- skip;
- ignore.

Bulk actions:

- select all A1/A2;
- select all high-confidence;
- mark common words as known;
- reset selection.

### Study Session

The first version can be simple:

- flashcard-like prompt;
- show example sentence;
- reveal explanation;
- mark result: again/good/easy;
- save answer;
- update progress.

Later versions can add:

- multiple choice;
- fill in the blank;
- sentence construction;
- pronunciation;
- grammar-specific tasks.

### Repetitions

Use SRS cards generated from selected learning units.

Show:

- due count;
- card prompt;
- answer/explanation;
- grading actions;
- session summary.

The current repetitions screen already exists, but it needs real cards from user selection.

### My Words

Show user's personal vocabulary:

- learning;
- known;
- ignored;
- weak;
- mastered.

Filters:

- language;
- level;
- type;
- source content;
- status.

Actions:

- move to learning;
- mark known;
- ignore;
- ask AI;
- review now.

### My Progress

Keep the existing progress idea, but make it more learning-oriented:

- total selected;
- learned today;
- streak;
- daily goal;
- due repetitions;
- weak words;
- progress by language;
- progress by level;
- progress by content.

### Add YouTube Content

For user-submitted content:

- submit YouTube URL;
- choose source language;
- choose target language;
- optional level/topic;
- show processing status;
- show failure reason if transcript unavailable;
- allow manual transcript paste if enabled for users.

The user should understand whether content is:

- waiting;
- fetching transcript;
- analyzing;
- waiting for admin review;
- ready;
- failed.

## Backend Needs

### User Learning Unit State

Add or refine a user-specific state model:

- `selected`;
- `known`;
- `learning`;
- `ignored`;
- `learned`;
- `weak`;
- `mastered`.

This should be separate from global `Lexeme` records.

### SRS Creation Rules

Create SRS cards only when user chooses `learn` or starts study for selected items.

Do not create SRS cards for:

- ignored items;
- already known items unless user opts in;
- rejected/admin-unapproved candidates.

### Multilingual Support

Every user-facing learning unit should carry:

- source language;
- target language;
- optional explanation language;
- CEFR level per source language;
- examples from source content.

The app should not assume English-only.

### Submission Status API

Expose user-friendly statuses for submitted content:

- accepted;
- transcript needed;
- analyzing;
- in review;
- ready;
- failed.

Do not leak internal job names into user UI.

## Implementation Stages

### Stage 1: Navigation And Entry

- Make `/app` the real user entry.
- Add dashboard.
- Ensure logged-out and logged-in flows are clear.

### Stage 2: Choose Learning Units

- Add learning unit selection API.
- Add selection screen.
- Save selected/known/ignored states.

### Stage 3: SRS Integration

- Create cards from selected items.
- Make repetitions useful with real content.
- Add summary after study sessions.

### Stage 4: Content Submission Status

- Improve Add YouTube page.
- Show pipeline status in user-friendly language.
- Add manual transcript fallback later if desired.

### Stage 5: Progress And Recommendations

- Use selected/known/weak states for recommendations.
- Improve progress dashboard.
- Recommend content based on language, level, weak words, and selected goals.

## Risks

- Too many learning unit types can overwhelm users. Start with simple grouping and filters.
- AI levels may be wrong. Allow user/admin correction.
- If content has too many extracted units, default selection rules are important.
- Without a polished dashboard, users may not understand where to start.

## First Useful Milestone

User can open one ready content item, choose several extracted words/phrases, mark some as known, start a study session, and see due SRS cards after learning.
