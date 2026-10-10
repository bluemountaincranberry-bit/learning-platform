<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { ArrowLeft, Check, History, Plus, RotateCcw, Send, Sparkles, Trash2 } from 'lucide-vue-next';
import { grammarApi } from '../domains/content';
import type { GrammarRuleEditorDraft, GrammarRuleEditorRevision, GrammarRuleEditorRule, GrammarRuleEditorTurn } from '../types';
import UiButton from '../shared/ui/UiButton.vue';
import ChatMessage from '../shared/ui/ChatMessage.vue';
import ChatComposerInput from '../shared/ui/ChatComposerInput.vue';
import MarkdownContent from '../shared/ui/MarkdownContent.vue';

const route = useRoute();
const router = useRouter();
const ruleId = computed(() => String(route.params.id));
const rule = ref<GrammarRuleEditorRule | null>(null);
const draft = ref<GrammarRuleEditorDraft | null>(null);
const savedDraft = ref<GrammarRuleEditorDraft | null>(null);
const messages = ref<GrammarRuleEditorTurn[]>([]);
const revisions = ref<GrammarRuleEditorRevision[]>([]);
const inputText = ref('');
const activePanel = ref<'chat' | 'draft'>('chat');
const loading = ref(true);
const sending = ref(false);
const saving = ref(false);
const loadingRevisions = ref(false);
const loadingOlderRevisions = ref(false);
const nextRevisionPage = ref<number | null>(null);
const showingHistory = ref(false);
const previewingBody = ref(false);
const error = ref('');
const aiUnavailable = ref(false);
const stale = ref(false);
const failedInstruction = ref('');
const conversationEnd = ref<HTMLElement | null>(null);
let previousBodyOverflow = '';

const hasChanges = computed(() => draft.value !== null && savedDraft.value !== null
    && JSON.stringify(draft.value) !== JSON.stringify(savedDraft.value));
const canSend = computed(() => inputText.value.trim().length >= 2 && !sending.value && !aiUnavailable.value);

function cloneDraft(value: GrammarRuleEditorDraft): GrammarRuleEditorDraft {
    return JSON.parse(JSON.stringify(value)) as GrammarRuleEditorDraft;
}

function draftFromRule(value: GrammarRuleEditorRule): GrammarRuleEditorDraft {
    return {
        title: value.title,
        summary: value.summary,
        body: value.body,
        examples: value.examples.map((example) => ({
            id: example.id,
            language: example.language,
            example: example.example,
            translation: example.translation,
            is_primary: example.is_primary,
            sort_order: example.sort_order,
        })),
    };
}

async function loadRule(): Promise<void> {
    loading.value = true;
    error.value = '';
    stale.value = false;
    try {
        const response = await grammarApi.getEditorRule(ruleId.value);
        rule.value = response.rule;
        draft.value = draftFromRule(response.rule);
        savedDraft.value = cloneDraft(draft.value);
    } catch {
        error.value = 'This rule is unavailable for editing. Return to the grammar page and try again.';
    } finally {
        loading.value = false;
    }
}

function responseMessage(exception: unknown, fallback: string): string {
    const value = exception as { response?: { status?: number; data?: { message?: string } } };
    if (value.response?.status === 409) {
        stale.value = true;
        return 'This rule changed while you were editing. Reload the latest version before applying your draft.';
    }
    if (value.response?.status === 503) {
        aiUnavailable.value = true;
        return value.response.data?.message ?? 'AI editing is unavailable right now. Your draft is still here, and you can keep editing it by hand.';
    }
    return value.response?.data?.message ?? fallback;
}

async function sendInstruction(value = inputText.value, retry = false): Promise<void> {
    const instruction = value.trim();
    if (!instruction || !draft.value || sending.value) return;

    const previousConversation = messages.value.slice(-12).map((message) => ({ ...message }));
    if (retry && previousConversation.at(-1)?.role === 'user' && previousConversation.at(-1)?.content === instruction) {
        previousConversation.pop();
    }
    if (!retry) messages.value.push({ role: 'user', content: instruction });
    inputText.value = '';
    failedInstruction.value = instruction;
    error.value = '';
    sending.value = true;

    try {
        const response = await grammarApi.proposeGrammarEdit(ruleId.value, {
            instruction,
            conversation: previousConversation,
            draft: cloneDraft(draft.value),
        });
        draft.value = response.proposal;
        messages.value.push({ role: 'assistant', content: response.message || 'I’ve updated the draft. Review the explanation and examples before applying it.' });
        activePanel.value = 'draft';
        failedInstruction.value = '';
        await nextTick();
        conversationEnd.value?.scrollIntoView({ behavior: 'smooth', block: 'end' });
    } catch (exception) {
        error.value = responseMessage(exception, 'I couldn’t prepare a draft. Your rule and current edits are unchanged. Try again.');
    } finally {
        sending.value = false;
    }
}

function addExample(): void {
    if (!draft.value || draft.value.examples.length >= 20) return;
    draft.value.examples.push({
        language: rule.value?.language ?? 'en', example: '', translation: '', is_primary: false,
        sort_order: (draft.value.examples.length + 1) * 10,
    });
}

function removeExample(index: number): void {
    draft.value?.examples.splice(index, 1);
    draft.value?.examples.forEach((example, order) => { example.sort_order = (order + 1) * 10; });
}

async function applyDraft(): Promise<void> {
    if (!rule.value || !draft.value || !hasChanges.value || saving.value || stale.value) return;
    saving.value = true;
    error.value = '';
    try {
        const response = await grammarApi.applyGrammarEdit(ruleId.value, draft.value, rule.value.editor_version);
        rule.value = response.rule;
        draft.value = draftFromRule(response.rule);
        savedDraft.value = cloneDraft(draft.value);
        messages.value.push({ role: 'assistant', content: 'Saved. The grammar page now shows your edited rule.' });
        revisions.value = [];
        nextRevisionPage.value = null;
        showingHistory.value = false;
    } catch (exception) {
        error.value = responseMessage(exception, 'The draft could not be saved. Your changes are still here; try again.');
    } finally {
        saving.value = false;
    }
}

async function loadRevisions(): Promise<void> {
    if (showingHistory.value) {
        showingHistory.value = false;
        return;
    }
    showingHistory.value = true;
    loadingRevisions.value = true;
    try {
        const response = await grammarApi.getGrammarEditRevisions(ruleId.value);
        revisions.value = response.revisions;
        nextRevisionPage.value = response.next_page;
    } catch {
        error.value = 'Revision history could not be loaded. Try again.';
    } finally {
        loadingRevisions.value = false;
    }
}

async function loadOlderRevisions(): Promise<void> {
    if (nextRevisionPage.value === null || loadingOlderRevisions.value) return;
    loadingOlderRevisions.value = true;
    try {
        const response = await grammarApi.getGrammarEditRevisions(ruleId.value, nextRevisionPage.value);
        revisions.value.push(...response.revisions);
        nextRevisionPage.value = response.next_page;
    } catch {
        error.value = 'Older versions could not be loaded. Try again.';
    } finally {
        loadingOlderRevisions.value = false;
    }
}

async function restoreRevision(revision: GrammarRuleEditorRevision): Promise<void> {
    if (!rule.value || saving.value || stale.value) return;
    saving.value = true;
    error.value = '';
    try {
        const response = await grammarApi.restoreGrammarEditRevision(ruleId.value, revision.id, rule.value.editor_version);
        rule.value = response.rule;
        draft.value = draftFromRule(response.rule);
        savedDraft.value = cloneDraft(draft.value);
        revisions.value = [];
        nextRevisionPage.value = null;
        showingHistory.value = false;
        messages.value.push({ role: 'assistant', content: 'Restored that version. The current content was saved as a new revision.' });
    } catch (exception) {
        error.value = responseMessage(exception, 'That version could not be restored. Your current draft is unchanged.');
    } finally {
        saving.value = false;
    }
}

function resetDraft(): void {
    if (savedDraft.value) draft.value = cloneDraft(savedDraft.value);
}

function goToRule(): void {
    router.push({ name: 'grammar.details', params: { id: ruleId.value } });
}

function onBeforeUnload(event: BeforeUnloadEvent): void {
    if (!hasChanges.value) return;
    event.preventDefault();
    event.returnValue = '';
}

onBeforeRouteLeave(() => !hasChanges.value || window.confirm('Discard your unsaved grammar draft?'));

onMounted(() => {
    previousBodyOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    window.addEventListener('beforeunload', onBeforeUnload);
    void loadRule();
});

onUnmounted(() => {
    document.body.style.overflow = previousBodyOverflow;
    window.removeEventListener('beforeunload', onBeforeUnload);
});
</script>

<template>
    <main class="fixed inset-0 z-[100] flex h-[100dvh] flex-col overflow-hidden bg-background text-fg" aria-label="AI grammar editor">
        <header class="flex shrink-0 items-center gap-2 border-b border-border px-3 py-2.5 pt-[max(env(safe-area-inset-top),0.625rem)] sm:px-5">
            <UiButton variant="ghost" size="icon-touch" aria-label="Return to grammar rule" title="Return to rule" @click="goToRule">
                <ArrowLeft :size="19" aria-hidden="true" />
            </UiButton>
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-base font-semibold leading-5">AI grammar editor</h1>
                <p class="truncate text-xs text-muted-foreground">{{ rule?.title ?? 'Loading rule…' }}</p>
            </div>
            <UiButton v-if="rule" variant="ghost" size="touch" class="shrink-0" @click="goToRule">Rule page</UiButton>
        </header>

        <nav class="grid shrink-0 grid-cols-2 border-b border-border bg-background lg:hidden" role="tablist" aria-label="Editor panels">
            <button type="button" role="tab" :aria-selected="activePanel === 'chat'" class="min-h-11 border-b-2 px-3 text-sm font-medium" :class="activePanel === 'chat' ? 'border-primary text-fg' : 'border-transparent text-muted-foreground'" @click="activePanel = 'chat'">
                Chat
            </button>
            <button type="button" role="tab" :aria-selected="activePanel === 'draft'" class="min-h-11 border-b-2 px-3 text-sm font-medium" :class="activePanel === 'draft' ? 'border-primary text-fg' : 'border-transparent text-muted-foreground'" @click="activePanel = 'draft'">
                Review draft<span v-if="hasChanges" class="ml-1.5 text-primary" aria-label="Unsaved changes">•</span>
            </button>
        </nav>

        <div v-if="loading" class="flex min-h-0 flex-1 items-center justify-center text-sm text-muted-foreground" role="status">Loading grammar rule…</div>
        <div v-else-if="error && !rule" class="mx-auto my-auto max-w-md px-5 text-center">
            <p class="text-sm leading-6 text-muted-foreground">{{ error }}</p>
            <UiButton variant="secondary" class="mt-4" @click="goToRule">Return to grammar rule</UiButton>
        </div>

        <div v-else-if="rule && draft" class="grid min-h-0 flex-1 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <section class="min-w-0 flex-col lg:flex" :class="activePanel === 'chat' ? 'flex' : 'hidden lg:flex'" aria-label="AI conversation">
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-5 sm:px-6">
                    <div class="mx-auto flex w-full max-w-2xl flex-col gap-4">
                        <div v-if="messages.length === 0" class="py-4 sm:py-10">
                            <div class="mb-5 flex items-start gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"><Sparkles :size="19" aria-hidden="true" /></div>
                                <div>
                                    <h2 class="text-lg font-semibold">Improve {{ rule.title }}</h2>
                                    <p class="mt-1 text-sm leading-6 text-muted-foreground">Ask for a clearer explanation, a simpler version, or better examples. Nothing is saved until you apply the reviewed draft.</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="prompt in ['Make the explanation easier to read', 'Simplify this for a beginner', 'Add natural examples and translations', 'Explain common mistakes']" :key="prompt" type="button" class="min-h-10 rounded-full border border-border bg-surface px-3 text-left text-sm text-fg-secondary transition-colors hover:bg-surface-alt hover:text-fg" :disabled="sending || aiUnavailable" @click="sendInstruction(prompt)">
                                    {{ prompt }}
                                </button>
                            </div>
                        </div>

                        <div v-for="(message, index) in messages" :key="index">
                            <ChatMessage :role="message.role" :content="message.content" />
                        </div>
                        <ChatMessage v-if="sending" role="assistant" loading loading-label="Updating the draft…" />
                        <ChatMessage v-if="error && rule" role="assistant" :error="error" :retryable="Boolean(failedInstruction) && !sending" :loading="sending" @retry="sendInstruction(failedInstruction, true)" />
                        <div ref="conversationEnd" />
                    </div>
                </div>

                <div v-if="aiUnavailable" class="border-t border-warning-border bg-warning-bg px-4 py-2 text-sm text-warning-fg sm:px-6">AI is unavailable. You can still edit and apply this draft by hand.</div>
                <form class="shrink-0 border-t border-border bg-surface px-3 pt-3 pb-[max(env(safe-area-inset-bottom),0.75rem)] sm:px-5 sm:py-4" @submit.prevent="sendInstruction()">
                    <div class="mx-auto flex w-full max-w-2xl flex-col gap-2">
                        <ChatComposerInput v-model="inputText" class="w-full" placeholder="Ask for a change…" aria-label="Message the AI editor" :disabled="sending || aiUnavailable" @submit="sendInstruction()" />
                        <div class="flex items-center justify-between gap-2">
                            <p class="min-w-0 text-xs text-muted-foreground">AI prepares a draft for your review.</p>
                            <UiButton variant="primary" size="touch" type="submit" :disabled="!canSend" class="shrink-0"><Send :size="16" aria-hidden="true" /> Send</UiButton>
                        </div>
                    </div>
                </form>
            </section>

            <section class="min-w-0 flex-col border-l border-border bg-surface/40 lg:flex" :class="activePanel === 'draft' ? 'flex' : 'hidden lg:flex'" aria-label="Review and edit draft">
                <div class="flex shrink-0 items-center justify-between gap-2 border-b border-border px-4 py-3 sm:px-5">
                    <div class="min-w-0">
                        <h2 class="font-semibold">Review draft</h2>
                        <p class="text-xs text-muted-foreground">Edit any field before applying.</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <UiButton variant="ghost" size="touch" :disabled="!hasChanges" title="Discard draft edits" @click="resetDraft"><RotateCcw :size="15" aria-hidden="true" /><span class="hidden sm:inline">Reset</span></UiButton>
                        <UiButton variant="ghost" size="touch" :disabled="loadingRevisions" title="Revision history" @click="loadRevisions"><History :size="16" aria-hidden="true" /><span class="hidden sm:inline">History</span></UiButton>
                    </div>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4 pb-6 sm:px-5">
                    <div v-if="stale" class="mb-4 rounded-lg border border-warning-border bg-warning-bg p-3 text-sm text-warning-fg" role="alert">
                        <p>{{ error }}</p>
                        <UiButton variant="secondary" size="sm" class="mt-2" @click="loadRule">Reload latest rule</UiButton>
                    </div>

                    <div v-if="showingHistory" class="mb-5 border-b border-border pb-4">
                        <h3 class="mb-3 text-sm font-semibold">Applied versions</h3>
                        <p v-if="loadingRevisions" class="text-sm text-muted-foreground">Loading history…</p>
                        <p v-else-if="revisions.length === 0" class="text-sm text-muted-foreground">No editor changes have been applied yet.</p>
                        <ol v-else class="space-y-3">
                            <li v-for="revision in revisions" :key="revision.id" class="flex items-center justify-between gap-3 border-t border-border pt-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ revision.new.title }}</p>
                                    <time class="text-xs text-muted-foreground">{{ new Date(revision.created_at).toLocaleString() }}</time>
                                </div>
                                <UiButton variant="secondary" size="sm" :disabled="saving || stale" @click="restoreRevision(revision)">Restore</UiButton>
                            </li>
                        </ol>
                        <UiButton v-if="nextRevisionPage !== null" variant="ghost" size="sm" class="mt-3" :disabled="loadingOlderRevisions" @click="loadOlderRevisions">{{ loadingOlderRevisions ? 'Loading…' : 'Load older versions' }}</UiButton>
                    </div>

                    <div class="space-y-4">
                        <label class="block space-y-1.5">
                            <span class="text-sm font-medium">Rule title</span>
                            <input v-model="draft.title" maxlength="255" class="min-h-11 w-full rounded-md border border-border bg-background px-3 text-base text-fg outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                        </label>

                        <label class="block space-y-1.5">
                            <span class="text-sm font-medium">Short summary</span>
                            <textarea v-model="draft.summary" rows="3" maxlength="2000" class="w-full resize-y rounded-md border border-border bg-background px-3 py-2.5 text-sm leading-6 text-fg outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="A concise learner-friendly summary" />
                        </label>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <label for="rule-body" class="text-sm font-medium">Explanation</label>
                                <div class="inline-flex rounded-md border border-border p-0.5" role="group" aria-label="Explanation mode">
                                    <button type="button" class="min-h-8 rounded px-2 text-xs" :class="!previewingBody ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="previewingBody = false">Edit</button>
                                    <button type="button" class="min-h-8 rounded px-2 text-xs" :class="previewingBody ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" @click="previewingBody = true">Preview</button>
                                </div>
                            </div>
                            <textarea v-if="!previewingBody" id="rule-body" v-model="draft.body" rows="12" maxlength="12000" class="w-full resize-y rounded-md border border-border bg-background px-3 py-2.5 font-mono text-sm leading-6 text-fg outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="Explain the form and when to use it…" />
                            <div v-else class="min-h-36 rounded-md border border-border bg-background p-3 text-sm leading-6"><MarkdownContent :content="draft.body || '_No explanation yet._'" /></div>
                        </div>

                        <div class="space-y-3 border-t border-border pt-4">
                            <div class="flex items-center justify-between gap-2">
                                <div>
                                    <h3 class="text-sm font-semibold">Examples</h3>
                                    <p class="text-xs text-muted-foreground">Examples removed here are archived, so user history stays intact.</p>
                                </div>
                                <UiButton variant="secondary" size="sm" :disabled="draft.examples.length >= 20" @click="addExample"><Plus :size="15" aria-hidden="true" /> Add</UiButton>
                            </div>
                            <div v-if="draft.examples.length === 0" class="rounded-md border border-dashed border-border-strong p-4 text-sm text-muted-foreground">Add at least one example before applying.</div>
                            <article v-for="(example, index) in draft.examples" :key="example.id ?? `new-${index}`" class="space-y-2 border-t border-border pt-3 first:border-0 first:pt-0">
                                <div class="flex items-center gap-2">
                                    <span class="min-w-0 flex-1 text-xs font-medium text-muted-foreground">Example {{ index + 1 }}<span v-if="example.id" class="ml-1.5">· existing</span></span>
                                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" :aria-label="`Remove example ${index + 1}`" @click="removeExample(index)"><Trash2 :size="16" aria-hidden="true" /></button>
                                </div>
                                <textarea v-model="example.example" rows="2" maxlength="500" class="w-full resize-y rounded-md border border-border bg-background px-3 py-2 text-sm leading-6 outline-none focus-visible:ring-2 focus-visible:ring-ring" :aria-label="`Example ${index + 1}`" placeholder="Example sentence" />
                                <input v-model="example.translation" maxlength="500" class="min-h-10 w-full rounded-md border border-border bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring" :aria-label="`Translation for example ${index + 1}`" placeholder="Translation" />
                            </article>
                        </div>
                    </div>
                </div>

                <footer class="shrink-0 border-t border-border bg-background px-4 py-3 pb-[max(env(safe-area-inset-bottom),0.75rem)] sm:px-5">
                    <div v-if="error && !stale" class="mb-2 text-sm text-destructive" role="alert">{{ error }}</div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="min-w-0 truncate text-xs text-muted-foreground">{{ hasChanges ? 'Unapplied changes' : 'No changes to apply' }}</span>
                        <UiButton variant="primary" size="touch" class="shrink-0" :disabled="!hasChanges || saving || stale || !draft.title.trim() || draft.examples.length === 0 || draft.examples.some((example) => !example.example.trim())" @click="applyDraft">
                            <Check :size="16" aria-hidden="true" /> {{ saving ? 'Saving…' : 'Apply changes' }}
                        </UiButton>
                    </div>
                </footer>
            </section>
        </div>
    </main>
</template>
