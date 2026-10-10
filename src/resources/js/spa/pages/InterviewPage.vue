<script setup lang="ts">
import VoiceDictationControl from '../shared/ui/VoiceDictationControl.vue';
import { useInterviewPreparation } from '../domains/interview';

const {
    questions, revisitQuestions, topics, availableTags, profile, search, topicId, state, tag,
    busy, error, notice, selected, russian, goalDraft, levelDraft, skillsDraft, projectsDraft,
    storiesDraft, milestoneDraft, milestoneDateDraft, saving, hasMore, loadingMore, addQuestionOpen,
    editingQuestion, newPromptEn, newPromptRu, newTags, draftPromptEn, draftPromptRu, draftTags,
    newTopicName, newTopicParent, practiceSession, practiceMessage, practiceVoice, sendingPractice,
    startingPractice, recentSessions, aiDrafts, practiceQuestionCount, practiceFocus, practiceDifficulty,
    translationProposal, translating, suggestedTopics, activePractice, topicOptions, topicName, load,
    decideAiDraft, refreshAiDrafts, currentPage, loadMore, saveAnswer, saveProfile, startQuestionEdit,
    suggestTranslation, useTranslationProposal, saveQuestion, savePreparationState, createQuestion,
    createTopic, startPractice, refreshPractice, retryPracticeRefresh, sendPracticeMessage,
    finishPractice, reopenPractice, continuePreparation, revisitTopic, pinInterviewVoice,
} = useInterviewPreparation();
</script>

<template>
    <main class="mx-auto w-full max-w-6xl space-y-5 px-4 py-5 sm:px-6">
        <p v-if="notice" class="rounded-spa border border-success/40 bg-success/10 p-3 text-sm text-success" role="status">{{ notice }}</p>
        <header class="space-y-1">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-primary">Interview preparation</p>
            <h1 class="text-2xl font-semibold text-fg">Build confidence one answer at a time</h1>
            <p class="text-sm text-muted-foreground">Your questions and preparation profile are private to your account.</p>
        </header>

        <section class="grid gap-3 rounded-spa-lg border border-primary/20 bg-primary/5 p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center" aria-label="Interview preparation home">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-primary">Your preparation</p>
                <p class="mt-1 break-words text-sm font-medium text-fg">{{ profile?.careerGoal || 'Set a career goal to guide your practice.' }}</p>
                <div v-if="suggestedTopics.length" class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="text-xs text-muted-foreground">Suggested topics to revisit:</span>
                    <button v-for="topic in suggestedTopics" :key="topic.id" class="min-h-9 rounded-full border border-border bg-surface px-3 text-xs text-fg" @click="revisitTopic(topic.id)">{{ topic.label }} · {{ topic.revisitCount }}</button>
                </div>
                <p v-else class="mt-2 text-xs text-muted-foreground">No topics need extra practice right now.</p>
            </div>
            <button class="min-h-11 rounded-spa bg-primary px-4 text-sm font-semibold text-white disabled:opacity-50" :disabled="startingPractice" @click="continuePreparation">{{ activePractice ? 'Continue practice' : 'Continue preparation' }}</button>
        </section>

        <section class="rounded-spa-lg border border-border bg-surface p-4 sm:p-5" aria-label="Interview practice">
            <section v-if="aiDrafts.length" class="mb-4 rounded-spa border border-primary/30 bg-surface-alt p-3" aria-label="AI proposals for review">
                <h2 class="font-semibold text-fg">AI proposals · review before adding</h2>
                <article v-for="draft in aiDrafts" :key="draft.id" class="mt-3 rounded-spa border border-border bg-surface p-3">
                    <template v-if="draft.kind === 'question'">
                        <p class="text-sm font-medium text-fg">{{ draft.promptEn }}</p><p v-if="draft.promptRu" class="mt-1 text-sm text-muted-foreground">{{ draft.promptRu }}</p>
                        <p class="mt-2 text-xs text-muted-foreground">{{ topicName(draft.topicId) ?? 'No topic' }}<span v-if="draft.tags.length"> · {{ draft.tags.join(', ') }}</span></p>
                    </template>
                    <dl v-else-if="draft.kind === 'profile'" class="space-y-1 text-sm"><div v-for="(change, index) in draft.changes" :key="`${change.label}-${index}`" class="grid grid-cols-[8rem_1fr] gap-2"><dt class="text-muted-foreground">{{ change.label }}</dt><dd class="break-words text-fg">{{ change.value }}</dd></div></dl>
                    <div v-else-if="draft.kind === 'answer'" class="space-y-2 text-sm"><p class="font-medium text-fg">{{ draft.questionPromptEn }} · {{ draft.variant }} answer</p><p v-if="draft.questionPromptRu" class="text-xs text-muted-foreground">{{ draft.questionPromptRu }}</p><div class="grid gap-2 sm:grid-cols-2"><div><p class="text-xs text-muted-foreground">English</p><p class="break-words text-fg">{{ draft.textEn ?? '(empty — clears this answer)' }}</p></div><div><p class="text-xs text-muted-foreground">Russian</p><p class="break-words text-fg">{{ draft.textRu ?? '(empty — clears this answer)' }}</p></div></div></div>
                    <div v-else-if="draft.kind === 'observation'" class="space-y-2 text-sm"><p class="font-medium text-fg">{{ draft.questionPromptEn }}</p><p v-if="draft.questionPromptRu" class="text-xs text-muted-foreground">{{ draft.questionPromptRu }}</p><p class="break-words text-fg"><span class="font-semibold">Evidence:</span> “{{ draft.evidence }}”</p><p class="break-words text-muted-foreground">{{ draft.reason }}</p><p class="font-medium text-primary">Suggested state: {{ draft.preparationState.replace('_', ' ') }}</p></div>
                    <div v-else-if="draft.kind === 'pattern_observation'" class="space-y-2 text-sm"><p class="font-semibold text-fg">Repeated {{ draft.patternType }} pattern</p><p class="break-words text-fg">{{ draft.summary }}</p><ul class="space-y-2"><li v-for="example in draft.examples" :key="`${example.sessionId}-${example.questionId}`" class="rounded-spa bg-surface-alt p-2"><p class="break-words font-medium text-fg">{{ example.questionPromptEn }}</p><p class="break-words text-muted-foreground">“{{ example.evidence }}”</p></li></ul></div>
                    <p v-else class="text-sm text-fg">Add <strong>{{ draft.lemma }}</strong> to your English vocabulary?</p>
                    <div class="mt-3 flex gap-2"><button class="min-h-11 rounded-spa bg-primary px-3 text-sm font-semibold text-white" @click="decideAiDraft(draft.id, 'confirm')">{{ draft.kind === 'question' ? 'Add question' : draft.kind === 'profile' ? 'Save profile updates' : draft.kind === 'answer' ? 'Save answer revision' : draft.kind === 'vocabulary' ? 'Add to My words' : draft.kind === 'pattern_observation' ? 'Save observation' : 'Update question state' }}</button><button class="min-h-11 rounded-spa border border-border px-3 text-sm text-fg" @click="decideAiDraft(draft.id, 'reject')">Discard</button></div>
                </article>
            </section>
            <template v-if="!practiceSession">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0"><h2 class="font-semibold text-fg">Practise with your Interview Agent</h2><p class="text-sm text-muted-foreground">Uses your confirmed profile and the selected question.</p></div>
                    <div class="flex flex-wrap gap-2">
                        <label class="flex min-w-0 items-center gap-2 text-xs text-muted-foreground">Focus
                            <input v-model="practiceFocus" maxlength="120" class="min-h-11 min-w-0 w-32 rounded-spa border border-border bg-surface-alt px-2 text-sm text-fg" placeholder="Optional" />
                        </label>
                        <label class="flex items-center gap-2 text-xs text-muted-foreground">Mock questions
                            <select v-model.number="practiceQuestionCount" class="min-h-11 rounded-spa border border-border bg-surface-alt px-2 text-sm text-fg"><option :value="1">1</option><option :value="3">3</option><option :value="5">5</option></select>
                        </label>
                        <label class="flex items-center gap-2 text-xs text-muted-foreground">Difficulty
                            <select v-model="practiceDifficulty" aria-label="Practice difficulty" class="min-h-11 rounded-spa border border-border bg-surface-alt px-2 text-sm text-fg"><option value="any">Match my profile</option><option value="beginner">Beginner</option><option value="intermediate">Intermediate</option><option value="advanced">Advanced</option></select>
                        </label>
                        <button class="min-h-11 rounded-spa border border-border px-3 text-sm text-fg disabled:opacity-50" :disabled="startingPractice" @click="startPractice('coached')">Start coached practice</button>
                        <button class="min-h-11 rounded-spa bg-primary px-3 text-sm font-semibold text-white disabled:opacity-50" :disabled="startingPractice" @click="startPractice('mock')">Start mock interview</button>
                    </div>
                </div>
            </template>
            <template v-else>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><h2 class="font-semibold text-fg">{{ practiceSession.mode === 'mock' ? 'Mock interview' : 'Coached practice' }}</h2><p class="text-xs text-muted-foreground">{{ practiceSession.status === 'active' ? 'Session saved · replies may take a moment' : 'Session complete' }}</p></div>
                    <button v-if="practiceSession.status === 'active'" class="min-h-11 rounded-spa border border-border px-3 text-sm text-fg" @click="finishPractice">Finish session</button>
                    <button class="min-h-11 rounded-spa border border-border px-3 text-sm text-fg" @click="practiceSession = null">Close</button>
                </div>
                <p v-for="question in practiceSession.questions" :key="question.id" class="mt-3 rounded-spa bg-surface-alt p-3 text-sm text-fg">{{ question.promptEn }}</p>
                <ol class="mt-3 max-h-72 space-y-2 overflow-y-auto" aria-label="Practice conversation">
                    <li v-for="message in practiceSession.messages" :key="message.id" class="max-w-full rounded-spa p-3 text-sm" :class="message.role === 'user' ? 'ml-6 bg-primary/10 text-fg' : 'mr-6 bg-surface-alt text-fg'">
                        <p class="whitespace-pre-wrap">{{ message.content }}</p>
                        <div v-if="message.voiceAudioUrl" class="mt-2 flex flex-wrap items-center gap-2 border-t border-border/60 pt-2">
                            <audio :src="message.voiceAudioUrl" controls class="h-9 max-w-full" aria-label="Interview answer recording" />
                            <button class="min-h-9 rounded-spa border border-border px-2 text-xs" @click="pinInterviewVoice(message.id, !message.voiceAudioPinned).catch(() => error = 'Recording retention could not be updated.')">{{ message.voiceAudioPinned ? 'Saved forever' : 'Keep forever' }}</button>
                            <span v-if="!message.voiceAudioPinned && message.voiceAudioExpiresAt" class="text-xs text-muted-foreground">Expires {{ new Date(message.voiceAudioExpiresAt).toLocaleDateString() }}</span>
                        </div>
                    </li>
                </ol>
                <form v-if="practiceSession.status === 'active'" class="mt-3 space-y-2" @submit.prevent="sendPracticeMessage">
                    <label class="sr-only" for="interview-practice-message">Your interview answer</label>
                    <textarea id="interview-practice-message" v-model="practiceMessage" rows="3" maxlength="10000" class="w-full rounded-spa border border-border bg-surface-alt p-3 text-sm text-fg" placeholder="Write your answer or ask for a hint" :disabled="sendingPractice" />
                    <VoiceDictationControl @ready="(text, audio, provider, language, keepForever) => { practiceMessage = text; practiceVoice = { audio, provider, language, keepForever }; }" @retention="(keepForever) => { if (practiceVoice) practiceVoice.keepForever = keepForever; }" @cleared="practiceVoice = null" />
                    <div class="flex flex-wrap items-center justify-between gap-2"><span class="text-xs text-muted-foreground" role="status">{{ sendingPractice ? 'Waiting for the Interview Agent…' : '' }}</span><button class="min-h-11 rounded-spa bg-primary px-4 text-sm font-semibold text-white disabled:opacity-50" :disabled="sendingPractice || !practiceMessage.trim()">Send answer</button></div>
                </form>
            </template>
            <details v-if="recentSessions.length" class="mt-3 border-t border-border pt-3">
                <summary class="min-h-11 cursor-pointer py-2 text-sm font-medium text-primary">Past practice sessions</summary>
                <ul class="space-y-2 pt-2"><li v-for="session in recentSessions" :key="session.id"><button class="min-h-11 w-full rounded-spa bg-surface-alt px-3 text-left text-sm text-fg" @click="reopenPractice(session.id)">{{ session.mode === 'mock' ? 'Mock interview' : 'Coached practice' }} · {{ session.status }} · {{ new Date(session.updatedAt).toLocaleDateString() }}</button></li></ul>
            </details>
        </section>

        <section class="rounded-spa-lg border border-border bg-surface p-4 sm:p-5" aria-labelledby="goal-heading">
            <div class="flex flex-wrap items-end gap-3">
                <label class="min-w-0 flex-1 text-sm font-medium text-fg" for="career-goal">
                    <span id="goal-heading" class="mb-2 block">Career goal</span>
                    <input id="career-goal" v-model="goalDraft" class="w-full rounded-spa border border-border bg-surface-alt px-3 py-2 text-fg" placeholder="For example, Junior Copilot Studio Developer" />
                </label>
                <button class="rounded-spa bg-primary px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="saving" @click="saveProfile">Save goal</button>
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <span v-for="milestone in profile?.milestones ?? []" :key="milestone.id ?? milestone.title" class="rounded-full bg-surface-alt px-3 py-1 text-xs text-muted-foreground">{{ milestone.title }}<template v-if="milestone.targetDate"> · {{ milestone.targetDate }}</template></span>
                <label class="sr-only" for="milestone">Add milestone</label>
                <input id="milestone" v-model="milestoneDraft" class="min-w-0 flex-1 rounded-spa border border-border bg-surface-alt px-3 py-2 text-sm text-fg" placeholder="Add a milestone" @keydown.enter.prevent="saveProfile" />
                <label class="sr-only" for="milestone-date">Optional target date</label>
                <input id="milestone-date" v-model="milestoneDateDraft" type="date" class="rounded-spa border border-border bg-surface-alt px-3 py-2 text-sm text-fg" />
                <button v-if="milestoneDraft.trim()" class="min-h-11 rounded-spa border border-border px-3 text-sm text-fg" :disabled="saving" @click="saveProfile">Add milestone</button>
            </div>
            <div v-if="profile?.observations?.length" class="mt-4 space-y-2" aria-label="Confirmed coaching observations"><article v-for="observation in profile.observations" :key="observation.id" class="rounded-spa border border-border bg-surface-alt p-3"><p class="text-xs font-semibold uppercase text-primary">Repeated {{ observation.patternType }}</p><p class="mt-1 text-sm text-fg">{{ observation.summary }}</p><ul class="mt-2 space-y-1 text-xs text-muted-foreground"><li v-for="example in observation.examples" :key="example.source_message_id" class="break-words">{{ example.question_prompt_en }} · “{{ example.evidence }}”</li></ul></article></div>
            <details class="mt-4">
                <summary class="min-h-11 cursor-pointer py-2 text-sm font-medium text-primary">Skills and experience details</summary>
                <div class="grid gap-3 pt-3 sm:grid-cols-2">
                    <label class="block text-xs text-muted-foreground">Experience level<input v-model="levelDraft" class="mt-1 w-full rounded-spa border border-border bg-surface-alt px-3 py-2 text-sm text-fg" placeholder="Junior / changing careers" /></label>
                    <label class="block text-xs text-muted-foreground">Skills, one per line<textarea v-model="skillsDraft" rows="3" class="mt-1 w-full rounded-spa border border-border bg-surface-alt p-2 text-sm text-fg" /></label>
                    <label class="block text-xs text-muted-foreground">Projects, one per line<textarea v-model="projectsDraft" rows="3" class="mt-1 w-full rounded-spa border border-border bg-surface-alt p-2 text-sm text-fg" /></label>
                    <label class="block text-xs text-muted-foreground">Experience stories, one per line<textarea v-model="storiesDraft" rows="3" class="mt-1 w-full rounded-spa border border-border bg-surface-alt p-2 text-sm text-fg" /></label>
                </div>
            </details>
        </section>

        <div class="grid min-w-0 gap-5 lg:grid-cols-[minmax(15rem,0.8fr)_minmax(0,1.4fr)]">
            <section class="min-w-0 space-y-3" aria-label="Question bank">
                <details class="rounded-spa-lg border border-border bg-surface p-3">
                    <summary class="min-h-11 cursor-pointer py-2 text-sm font-semibold text-fg">Organize topics</summary>
                    <form class="grid gap-2 pt-2" @submit.prevent="createTopic">
                        <label class="text-xs text-muted-foreground">Parent topic<select v-model="newTopicParent" class="mt-1 w-full rounded-spa border border-border bg-surface-alt px-3 py-2 text-sm text-fg"><option value="">Top level</option><option v-for="topic in topicOptions" :key="topic.id" :value="String(topic.id)">{{ topic.label }}</option></select></label>
                        <label class="text-xs text-muted-foreground">New topic name<input v-model="newTopicName" class="mt-1 w-full rounded-spa border border-border bg-surface-alt px-3 py-2 text-sm text-fg" /></label>
                        <button class="min-h-11 rounded-spa border border-border text-sm text-fg" :disabled="saving">Add topic</button>
                    </form>
                </details>
                <button class="w-full rounded-spa border border-primary/50 bg-primary/10 px-4 py-3 text-left text-sm font-semibold text-primary" :aria-expanded="addQuestionOpen" @click="addQuestionOpen = !addQuestionOpen">{{ addQuestionOpen ? 'Close new question' : '+ Add a question' }}</button>
                <form v-if="addQuestionOpen" class="space-y-2 rounded-spa-lg border border-border bg-surface p-3" @submit.prevent="createQuestion">
                    <label class="block text-xs text-muted-foreground">Question in English<input v-model="newPromptEn" required maxlength="5000" class="mt-1 w-full rounded-spa border border-border bg-surface-alt px-3 py-2 text-sm text-fg" /></label>
                    <label class="block text-xs text-muted-foreground">Russian translation<input v-model="newPromptRu" maxlength="5000" class="mt-1 w-full rounded-spa border border-border bg-surface-alt px-3 py-2 text-sm text-fg" /></label>
                    <label class="block text-xs text-muted-foreground">Tags, separated by commas<input v-model="newTags" class="mt-1 w-full rounded-spa border border-border bg-surface-alt px-3 py-2 text-sm text-fg" /></label>
                    <button class="min-h-11 w-full rounded-spa bg-primary px-4 text-sm font-semibold text-white disabled:opacity-50" :disabled="saving">Add to my bank</button>
                </form>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
                    <label class="sr-only" for="question-search">Search English and Russian questions</label>
                    <input id="question-search" v-model="search" class="min-w-0 rounded-spa border border-border bg-surface px-3 py-2 text-sm text-fg" placeholder="Search in English or Russian" />
                    <select v-model="topicId" class="min-w-0 rounded-spa border border-border bg-surface px-3 py-2 text-sm text-fg" aria-label="Filter by topic">
                        <option value="">All topics</option><option v-for="topic in topicOptions" :key="topic.id" :value="String(topic.id)">{{ topic.label }}</option>
                    </select>
                    <select v-model="state" class="min-w-0 rounded-spa border border-border bg-surface px-3 py-2 text-sm text-fg" aria-label="Filter by preparation state">
                        <option value="">All preparation states</option><option value="unpracticed">Unpracticed</option><option value="needs_practice">Needs practice</option><option value="confident">Confident</option>
                    </select>
                    <select v-model="tag" class="min-w-0 rounded-spa border border-border bg-surface px-3 py-2 text-sm text-fg" aria-label="Filter by tag">
                        <option value="">All tags</option><option v-for="name in availableTags" :key="name" :value="name">{{ name }}</option>
                    </select>
                </div>
                <div class="overflow-hidden rounded-spa-lg border border-border bg-surface">
                    <p v-if="busy" class="p-4 text-sm text-muted-foreground" role="status">Loading your questions…</p>
                    <p v-else-if="!questions.length" class="p-4 text-sm text-muted-foreground">No questions match these filters. Run <code>php artisan interview:seed-starter</code> to add the starter bank.</p>
                    <button v-for="question in questions" v-else :key="question.id" class="block w-full min-w-0 border-b border-border px-4 py-3 text-left last:border-0 hover:bg-surface-alt" :class="selected?.id === question.id ? 'bg-primary/10' : ''" @click="selected = question">
                        <span class="block break-words font-medium text-fg">{{ question.promptEn }}</span>
                        <span class="mt-1 block text-xs text-muted-foreground">{{ question.topic?.name ?? 'Unsorted' }} · {{ question.preparationState.replace('_', ' ') }}</span>
                    </button>
                    <button v-if="hasMore" class="min-h-11 w-full text-sm font-medium text-primary disabled:opacity-50" :disabled="loadingMore" @click="loadMore">{{ loadingMore ? 'Loading…' : 'Load more questions' }}</button>
                </div>
            </section>

            <section class="min-w-0 rounded-spa-lg border border-border bg-surface p-4 sm:p-5" aria-label="Question details">
                <div v-if="selected" class="space-y-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <template v-if="editingQuestion">
                                <label class="block text-xs text-muted-foreground">Question in English<textarea v-model="draftPromptEn" rows="2" class="mt-1 w-full rounded-spa border border-border bg-surface-alt p-2 text-base text-fg" /></label>
                                <button class="mt-1 min-h-9 rounded-spa border border-border px-3 text-xs text-fg disabled:opacity-50" :disabled="translating || !draftPromptEn.trim()" @click="suggestTranslation(draftPromptEn, 'ru', 'question')">{{ translating ? 'Suggesting…' : 'Suggest Russian translation' }}</button>
                                <label class="mt-2 block text-xs text-muted-foreground">Russian translation<textarea v-model="draftPromptRu" rows="2" class="mt-1 w-full rounded-spa border border-border bg-surface-alt p-2 text-sm text-fg" /></label>
                                <button class="mt-1 min-h-9 rounded-spa border border-border px-3 text-xs text-fg disabled:opacity-50" :disabled="translating || !draftPromptRu.trim()" @click="suggestTranslation(draftPromptRu, 'en', 'question')">{{ translating ? 'Suggesting…' : 'Suggest English translation' }}</button>
                                <label class="mt-2 block text-xs text-muted-foreground">Tags<input v-model="draftTags" class="mt-1 w-full rounded-spa border border-border bg-surface-alt px-3 py-2 text-sm text-fg" /></label>
                                <button class="mt-2 min-h-11 rounded-spa bg-primary px-3 text-sm font-semibold text-white" :disabled="saving" @click="saveQuestion">Save question</button>
                            </template>
                            <template v-else><h2 class="break-words text-xl font-semibold text-fg">{{ selected.promptEn }}</h2><p v-if="russian && selected.promptRu" class="mt-2 break-words text-muted-foreground">{{ selected.promptRu }}</p></template>
                        </div>
                        <div class="flex flex-wrap gap-2"><button v-if="!editingQuestion" class="min-h-11 rounded-spa border border-border px-3 text-sm text-fg" @click="startQuestionEdit">Edit question</button><button class="min-h-11 rounded-spa border border-border px-3 text-sm text-fg" @click="russian = !russian">{{ russian ? 'Hide Russian' : 'Show Russian' }}</button></div>
                    </div>
                    <div class="flex flex-wrap gap-2"><span v-for="tag in selected.tags" :key="tag" class="rounded-full bg-primary/10 px-2.5 py-1 text-xs text-primary">{{ tag }}</span></div>
                    <label class="block max-w-xs text-xs text-muted-foreground">Preparation state<select v-model="selected.preparationState" class="mt-1 w-full rounded-spa border border-border bg-surface-alt px-3 py-2 text-sm text-fg" @change="savePreparationState"><option value="unpracticed">Unpracticed</option><option value="needs_practice">Needs practice</option><option value="confident">Confident</option></select></label>
                    <article v-for="kind in (['short', 'full'] as const)" :key="kind" class="space-y-2 rounded-spa border border-border p-3">
                        <div class="flex items-center justify-between gap-2"><h3 class="font-semibold capitalize text-fg">{{ kind }} answer</h3><button v-if="selected.answers[kind]?.revisions.length" class="text-xs text-primary underline" @click="interviewApi.restoreRevision(selected.id, selected.answers[kind]!.id, selected.answers[kind]!.revisions[0].id).then((q) => selected = q)">Restore previous version</button></div>
                        <label class="block text-xs text-muted-foreground">English<textarea v-model="selected.answers[kind]!.en" rows="3" class="mt-1 w-full resize-y rounded-spa border border-border bg-surface-alt p-2 text-sm text-fg" placeholder="Write your answer in English" /></label>
                        <button class="min-h-9 rounded-spa border border-border px-3 text-xs text-fg disabled:opacity-50" :disabled="translating || !selected.answers[kind]?.en?.trim()" @click="suggestTranslation(selected.answers[kind]?.en ?? '', 'ru', 'answer', kind)">{{ translating ? 'Suggesting…' : 'Suggest Russian translation' }}</button>
                        <label class="block text-xs text-muted-foreground">Russian<textarea v-model="selected.answers[kind]!.ru" rows="2" class="mt-1 w-full resize-y rounded-spa border border-border bg-surface-alt p-2 text-sm text-fg" placeholder="Write your answer in Russian" /></label>
                        <button class="min-h-9 rounded-spa border border-border px-3 text-xs text-fg disabled:opacity-50" :disabled="translating || !selected.answers[kind]?.ru?.trim()" @click="suggestTranslation(selected.answers[kind]?.ru ?? '', 'en', 'answer', kind)">{{ translating ? 'Suggesting…' : 'Suggest English translation' }}</button>
                        <button class="rounded-spa bg-primary px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="saving" @click="saveAnswer(kind)">Save {{ kind }} answer</button>
                    </article>
                    <article v-if="translationProposal" class="rounded-spa border border-primary/30 bg-primary/5 p-3" aria-label="Translation proposal review">
                        <p class="text-xs font-semibold uppercase text-primary">Translation proposal · {{ translationProposal.targetLanguage === 'ru' ? 'Russian' : 'English' }}</p>
                        <p class="mt-1 break-words text-sm text-fg">{{ translationProposal.text }}</p>
                        <div class="mt-2 flex flex-wrap gap-2"><button class="min-h-10 rounded-spa bg-primary px-3 text-sm font-semibold text-white" @click="useTranslationProposal">Use suggestion</button><button class="min-h-10 rounded-spa border border-border px-3 text-sm text-fg" @click="translationProposal = null">Discard</button></div>
                        <p class="mt-2 text-xs text-muted-foreground">The saved answer remains unchanged until you use the suggestion and save the answer.</p>
                    </article>
                    <p class="text-xs text-muted-foreground">Answer changes keep the previous version so you can restore it.</p>
                </div>
                <p v-else class="py-8 text-center text-sm text-muted-foreground">Choose a question to review its answers.</p>
            </section>
        </div>
        <p v-if="error" class="rounded-spa border border-warning/40 bg-warning/10 p-3 text-sm text-warning" role="alert">{{ error }} <button class="underline" @click="practiceSession ? retryPracticeRefresh() : load()">{{ practiceSession ? 'Refresh session' : 'Retry' }}</button></p>
    </main>
</template>
