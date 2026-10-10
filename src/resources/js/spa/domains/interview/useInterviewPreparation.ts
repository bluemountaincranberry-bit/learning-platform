import { computed, onMounted, ref, watch } from 'vue';
import { translateApi } from '../ai';
import type { SpeechLanguage, SpeechProvider } from '../learning';
import { interviewApi } from './api';
import type { InterviewDraft, InterviewPracticeSession, InterviewProfile, InterviewQuestion, InterviewTopic } from './types';

export function useInterviewPreparation() {


const questions = ref<InterviewQuestion[]>([]);
const revisitQuestions = ref<InterviewQuestion[]>([]);
const topics = ref<InterviewTopic[]>([]);
const availableTags = ref<string[]>([]);
const profile = ref<InterviewProfile | null>(null);
const search = ref('');
const topicId = ref('');
const state = ref('');
const tag = ref('');
const busy = ref(true);
const error = ref('');
const notice = ref('');
const selected = ref<InterviewQuestion | null>(null);
const russian = ref(false);
const goalDraft = ref('');
const levelDraft = ref('');
const skillsDraft = ref('');
const projectsDraft = ref('');
const storiesDraft = ref('');
const milestoneDraft = ref('');
const milestoneDateDraft = ref('');
const saving = ref(false);
const hasMore = ref(false);
const loadingMore = ref(false);
const addQuestionOpen = ref(false);
const editingQuestion = ref(false);
const newPromptEn = ref('');
const newPromptRu = ref('');
const newTags = ref('');
const draftPromptEn = ref('');
const draftPromptRu = ref('');
const draftTags = ref('');
const newTopicName = ref('');
const newTopicParent = ref('');
const practiceSession = ref<InterviewPracticeSession | null>(null);
const practiceMessage = ref('');
const practiceVoice = ref<{ audio: Blob; provider: SpeechProvider; language: SpeechLanguage; keepForever: boolean } | null>(null);
const sendingPractice = ref(false);
const startingPractice = ref(false);
const recentSessions = ref<{ id: number; mode: 'coached' | 'mock'; status: 'active' | 'completed'; updatedAt: string }[]>([]);
const aiDrafts = ref<InterviewDraft[]>([]);
const practiceQuestionCount = ref(3);
const practiceFocus = ref('');
const practiceDifficulty = ref<InterviewPracticeSession['difficulty']>('any');
const translationProposal = ref<{ target: 'question' | 'answer'; variant?: 'short' | 'full'; targetLanguage: 'en' | 'ru'; text: string } | null>(null);
const translating = ref(false);
const suggestedTopics = computed(() => {
    const counts = new Map<number, number>();
    revisitQuestions.value.forEach((question) => {
        if (question.topic && question.preparationState === 'needs_practice') counts.set(question.topic.id, (counts.get(question.topic.id) ?? 0) + 1);
    });
    return topicOptions.value.map((topic) => ({ ...topic, revisitCount: counts.get(topic.id) ?? 0 }))
        .filter((topic) => topic.revisitCount > 0).sort((left, right) => right.revisitCount - left.revisitCount).slice(0, 3);
});
const activePractice = computed(() => recentSessions.value.find((session) => session.status === 'active') ?? null);

const topicOptions = computed(() => {
    const byId = new Map(topics.value.map((topic) => [topic.id, topic]));
    const label = (topic: InterviewTopic) => {
        const parts = [topic.name];
        let parent = topic.parentId ? byId.get(topic.parentId) : undefined;
        while (parent) { parts.unshift(parent.name); parent = parent.parentId ? byId.get(parent.parentId) : undefined; }
        return parts.join(' / ');
    };
    return topics.value.map((topic) => ({ ...topic, label: label(topic) }));
});

function topicName(id: number | null) {
    return id === null ? null : topicOptions.value.find((topic) => topic.id === id)?.label ?? null;
}

async function load(append = false) {
    busy.value = !append;
    error.value = '';
    try {
        const page = append ? Number(currentPage.value) + 1 : 1;
        const [topicData, questionPage, profileData, tagData, sessionData, draftData] = await Promise.all([
            interviewApi.topics(), interviewApi.questions({
                ...(search.value.trim() ? { search: search.value.trim() } : {}),
                ...(topicId.value ? { topic_id: topicId.value } : {}),
                ...(state.value ? { state: state.value } : {}),
                ...(tag.value ? { tag: tag.value } : {}),
                page: String(page),
            }), interviewApi.profile(), interviewApi.tags(), interviewApi.sessions(), interviewApi.drafts(),
        ]);
        const reviewQuestions: InterviewQuestion[] = [];
        let reviewPage = 1;
        let reviewHasMore = true;
        while (reviewHasMore) {
            const result = await interviewApi.questions({ per_page: '100', page: String(reviewPage) });
            reviewQuestions.push(...result.items);
            reviewHasMore = result.hasMore;
            reviewPage += 1;
        }
        topics.value = topicData;
        questions.value = append ? [...questions.value, ...questionPage.items] : questionPage.items;
        hasMore.value = questionPage.hasMore;
        currentPage.value = page;
        availableTags.value = tagData;
        recentSessions.value = sessionData;
        revisitQuestions.value = reviewQuestions;
        aiDrafts.value = draftData;
        profile.value = profileData;
        goalDraft.value = profileData.careerGoal ?? '';
        levelDraft.value = profileData.experienceLevel ?? '';
        skillsDraft.value = (profileData.skills ?? []).join('\n');
        projectsDraft.value = (profileData.projects ?? []).join('\n');
        storiesDraft.value = (profileData.experienceStories ?? []).join('\n');
        if (!selected.value || !questionPage.items.some((question) => question.id === selected.value?.id)) selected.value = questionPage.items[0] ?? null;
    } catch {
        error.value = 'Interview preparation could not load. Check your connection and try again.';
    } finally { busy.value = false; }
}

async function decideAiDraft(id: number, decision: 'confirm' | 'reject') {
    const draft = aiDrafts.value.find((item) => item.id === id);
    try {
        await interviewApi.decideDraft(id, decision);
        aiDrafts.value = aiDrafts.value.filter((draft) => draft.id !== id);
        if (decision === 'confirm') {
            if (draft?.kind === 'vocabulary') notice.value = `Added “${draft.lemma}” to My words.`;
            if (draft?.kind === 'observation') notice.value = `Question state updated to ${draft.preparationState.replace('_', ' ')}.`;
            if (draft?.kind === 'pattern_observation') notice.value = 'Repeated coaching observation saved to your profile.';
            await load();
        }
    } catch { error.value = 'This AI proposal could not be saved. Please try again.'; }
}

async function refreshAiDrafts() {
    aiDrafts.value = await interviewApi.drafts();
}

const currentPage = ref(1);

let timer: ReturnType<typeof setTimeout> | undefined;
watch([search, topicId, state, tag], () => { clearTimeout(timer); timer = setTimeout(load, 250); });
onMounted(load);

async function loadMore() {
    if (!hasMore.value || loadingMore.value) return;
    loadingMore.value = true;
    try { await load(true); } finally { loadingMore.value = false; }
}

async function saveAnswer(kind: 'short' | 'full') {
    if (!selected.value) return;
    saving.value = true;
    error.value = '';
    try {
        const answer = selected.value.answers[kind];
        selected.value = await interviewApi.updateQuestion(selected.value.id, {
            answers: { [kind]: { en: answer?.en ?? '', ru: answer?.ru ?? '' } },
        });
        questions.value = questions.value.map((question) => question.id === selected.value?.id ? selected.value! : question);
    } catch { error.value = 'The answer could not be saved. Your draft remains on screen.'; }
    finally { saving.value = false; }
}

async function saveProfile() {
    if (!profile.value) return;
    saving.value = true;
    try {
        profile.value = await interviewApi.saveProfile({ ...profile.value, careerGoal: goalDraft.value,
            experienceLevel: levelDraft.value,
            skills: skillsDraft.value.split('\n').map((value) => value.trim()).filter(Boolean),
            projects: projectsDraft.value.split('\n').map((value) => value.trim()).filter(Boolean),
            experienceStories: storiesDraft.value.split('\n').map((value) => value.trim()).filter(Boolean),
            milestones: milestoneDraft.value.trim() ? [...profile.value.milestones, { title: milestoneDraft.value.trim(), targetDate: milestoneDateDraft.value || null }] : profile.value.milestones });
        milestoneDraft.value = ''; milestoneDateDraft.value = '';
    } catch { error.value = 'The preparation profile could not be saved.'; }
    finally { saving.value = false; }
}

function startQuestionEdit() {
    if (!selected.value) return;
    draftPromptEn.value = selected.value.promptEn;
    draftPromptRu.value = selected.value.promptRu ?? '';
    draftTags.value = selected.value.tags.join(', ');
    editingQuestion.value = true;
}

async function suggestTranslation(source: string, targetLanguage: 'en' | 'ru', target: 'question' | 'answer', variant?: 'short' | 'full') {
    if (!source.trim()) return;
    translating.value = true;
    error.value = '';
    translationProposal.value = null;
    try {
        const result = await translateApi.translate(source.trim(), targetLanguage);
        translationProposal.value = { target, variant, targetLanguage, text: result.translation };
    } catch {
        error.value = 'A translation suggestion is unavailable right now. Your saved text is unchanged.';
    } finally { translating.value = false; }
}

function useTranslationProposal() {
    const proposal = translationProposal.value;
    if (!proposal) return;
    if (proposal.target === 'question') {
        if (proposal.targetLanguage === 'ru') draftPromptRu.value = proposal.text;
        else draftPromptEn.value = proposal.text;
    } else if (selected.value && proposal.variant) {
        selected.value.answers[proposal.variant]![proposal.targetLanguage] = proposal.text;
    }
    translationProposal.value = null;
}

async function saveQuestion() {
    if (!selected.value) return;
    saving.value = true;
    try {
        const saved = await interviewApi.updateQuestion(selected.value.id, {
            promptEn: draftPromptEn.value, promptRu: draftPromptRu.value, topicId: selected.value.topic?.id ?? null,
            tags: draftTags.value.split(',').map((value) => value.trim()).filter(Boolean),
        });
        selected.value = saved;
        questions.value = questions.value.map((question) => question.id === saved.id ? saved : question);
        editingQuestion.value = false;
    } catch { error.value = 'The question could not be saved.'; }
    finally { saving.value = false; }
}

async function savePreparationState() {
    if (!selected.value) return;
    const questionId = selected.value.id;
    try {
        selected.value = await interviewApi.updateQuestion(questionId, { preparationState: selected.value.preparationState });
        questions.value = questions.value.map((question) => question.id === questionId ? selected.value! : question);
    } catch { error.value = 'The preparation state could not be saved.'; }
}

async function createQuestion() {
    if (!newPromptEn.value.trim()) return;
    saving.value = true;
    try {
        const question = await interviewApi.createQuestion({
            promptEn: newPromptEn.value.trim(), promptRu: newPromptRu.value.trim() || null,
            topicId: topicId.value ? Number(topicId.value) : null,
            tags: newTags.value.split(',').map((value) => value.trim()).filter(Boolean),
        });
        questions.value.unshift(question);
        selected.value = question;
        newPromptEn.value = ''; newPromptRu.value = ''; newTags.value = ''; addQuestionOpen.value = false;
    } catch { error.value = 'The question could not be created.'; }
    finally { saving.value = false; }
}

async function createTopic() {
    if (!newTopicName.value.trim()) return;
    saving.value = true;
    try {
        const topic = await interviewApi.createTopic(newTopicName.value.trim(), newTopicParent.value ? Number(newTopicParent.value) : null);
        topics.value.push(topic);
        topicId.value = String(topic.id);
        newTopicName.value = ''; newTopicParent.value = '';
    } catch { error.value = 'The topic could not be created.'; }
    finally { saving.value = false; }
}

async function startPractice(mode: 'coached' | 'mock') {
    startingPractice.value = true;
    error.value = '';
    try {
        const selectedIds = mode === 'coached' && selected.value ? [selected.value.id] : [];
        practiceSession.value = await interviewApi.startSession(mode, selectedIds, mode === 'coached' ? 1 : practiceQuestionCount.value,
            topicId.value ? Number(topicId.value) : null, practiceFocus.value.trim() || null, practiceDifficulty.value);
    } catch { error.value = 'Interview practice could not start. The question bank remains available.'; }
    finally { startingPractice.value = false; }
}

async function refreshPractice() {
    if (!practiceSession.value) return;
    practiceSession.value = await interviewApi.getSession(practiceSession.value.id);
}

async function retryPracticeRefresh() {
    try {
        await refreshPractice();
        error.value = '';
    } catch {
        error.value = 'The practice session is still unavailable. Your submitted answer remains saved; try refreshing again.';
    }
}

async function sendPracticeMessage() {
    if (!practiceSession.value || !practiceMessage.value.trim()) return;
    const sessionId = practiceSession.value.id;
    const previousAssistantId = Math.max(0, ...practiceSession.value.messages.filter((message) => message.role === 'assistant').map((message) => message.id));
    const content = practiceMessage.value.trim();
    sendingPractice.value = true;
    practiceMessage.value = '';
    error.value = '';
    let submitted = false;
    try {
        await interviewApi.sendSessionMessage(sessionId, content, practiceVoice.value ?? undefined);
        submitted = true;
        practiceVoice.value = null;
        let receivedReply = false;
        for (let attempt = 0; attempt < 15; attempt += 1) {
            await new Promise((resolve) => setTimeout(resolve, 1000));
            const updated = await interviewApi.getSession(sessionId);
            practiceSession.value = updated;
            if (updated.messages.some((message) => message.role === 'assistant' && message.id > previousAssistantId)) {
                receivedReply = true;
                break;
            }
        }
        if (!receivedReply) error.value = 'Your answer was saved, but the Interview Agent has not replied yet. Refresh the session to check again.';
        await refreshAiDrafts();
    } catch {
        if (!submitted) practiceMessage.value = content;
        error.value = submitted
            ? 'Your answer was sent, but the Interview Agent is unavailable. Refresh the session to check for a reply.'
            : 'Your answer is still here, but could not be sent. Check your connection and try again.';
    }
    finally { sendingPractice.value = false; }
}

async function finishPractice() {
    if (!practiceSession.value) return;
    const current = practiceSession.value;
    if (current.mode === 'mock' && current.status === 'active') {
        try {
            const lastAssistantId = Math.max(0, ...current.messages.filter((message) => message.role === 'assistant').map((message) => message.id));
            await interviewApi.sendSessionMessage(current.id, 'The mock interview is complete. Please give me your feedback now.');
            for (let attempt = 0; attempt < 15; attempt += 1) {
                await new Promise((resolve) => setTimeout(resolve, 1000));
                const updated = await interviewApi.getSession(current.id);
                practiceSession.value = updated;
                if (updated.messages.some((message) => message.role === 'assistant' && message.id > lastAssistantId)) {
                    break;
                }
                if (attempt === 14) {
                    error.value = 'The Interview Agent has not replied yet. The mock session stays open so you can retry and receive feedback.';
                    return;
                }
            }
        } catch {
            error.value = 'The Interview Agent is unavailable. The mock session stays open so you can retry feedback later.';
            return;
        }
    }
    try {
        practiceSession.value = await interviewApi.completeSession(current.id);
        await load();
    }
    catch { error.value = 'This practice session could not be completed.'; }
}

async function reopenPractice(sessionId: number) {
    try { practiceSession.value = await interviewApi.getSession(sessionId); }
    catch { error.value = 'This practice history could not be opened.'; }
}

async function continuePreparation() {
    if (activePractice.value) {
        await reopenPractice(activePractice.value.id);
        return;
    }
    await startPractice('coached');
}

function revisitTopic(id: number) {
    topicId.value = String(id);
    state.value = 'needs_practice';
}

async function pinInterviewVoice(messageId: number, pinned: boolean) {
    const result = await interviewApi.pinVoiceRecording(messageId, pinned);
    const message = practiceSession.value?.messages.find((item) => item.id === messageId);
    if (message) { message.voiceAudioPinned = result.pinned; message.voiceAudioExpiresAt = result.expires_at; }
}
return {
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
};
}
