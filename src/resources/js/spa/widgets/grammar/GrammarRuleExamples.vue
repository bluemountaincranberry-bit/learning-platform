<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { EyeOff, Plus } from 'lucide-vue-next';
import SpeakButton from '../../shared/ui/SpeakButton.vue';
import UiBadge from '../../shared/ui/UiBadge.vue';
import UiButton from '../../shared/ui/UiButton.vue';
import UiSpinner from '../../shared/ui/UiSpinner.vue';
import { grammarApi } from '../../domains/content';
import type { GrammarRuleExample, GrammarRuleExampleGenerationStatus } from '../../types';
import { exampleSegments } from './exampleSegments';

/**
 * Rule page examples (VIK-39): grammar form highlighted, translation under
 * each sentence, typical mistakes, "More examples" (queued AI batch, polled
 * until done) and hiding a bad example for yourself.
 */
const props = withDefaults(defineProps<{
    ruleId: number;
    /** Examples already in the rule payload, shown until the list loads. */
    initialExamples?: GrammarRuleExample[];
    language?: string | null;
    authenticated?: boolean;
    /** Poll interval while AI examples are being written (tests shorten it). */
    pollMs?: number;
}>(), {
    initialExamples: () => [],
    language: null,
    authenticated: false,
    pollMs: 2000,
});

const POLL_LIMIT_MS = 90_000;

const examples = ref<GrammarRuleExample[]>([...props.initialExamples]);
const generating = ref(false);
const notice = ref('');
let pollTimer: ReturnType<typeof setTimeout> | null = null;
let pollStartedAt = 0;
let disposed = false;

const kindLabels: Record<string, string> = {
    negative: 'Negative',
    question: 'Question',
};

const rows = computed(() => examples.value.map((example) => ({
    example,
    segments: exampleSegments(example.example, example.target_spans),
})));

async function load(): Promise<GrammarRuleExampleGenerationStatus | null> {
    try {
        const data = await grammarApi.getExamples(props.ruleId);
        examples.value = data.examples;
        return data.generation.status;
    } catch {
        return null;
    }
}

function stopPolling(): void {
    if (pollTimer) clearTimeout(pollTimer);
    pollTimer = null;
}

function schedulePoll(previousCount: number): void {
    stopPolling();
    pollTimer = setTimeout(async () => {
        const status = await load();
        if (disposed) return;
        if (status === 'queued' || status === 'running') {
            if (Date.now() - pollStartedAt < POLL_LIMIT_MS) {
                schedulePoll(previousCount);
                return;
            }
            notice.value = 'Examples are taking longer than usual. Check back in a minute.';
        } else if (status === 'failed') {
            notice.value = 'Could not write new examples right now. Try again later.';
        } else if (examples.value.length <= previousCount) {
            notice.value = 'No new examples this time. Try again later.';
        }
        generating.value = false;
    }, props.pollMs);
}

async function moreExamples(): Promise<void> {
    if (generating.value) return;
    notice.value = '';
    generating.value = true;
    try {
        const { status } = await grammarApi.generateExamples(props.ruleId);
        if (status === 'queued' || status === 'active') {
            pollStartedAt = Date.now();
            schedulePoll(examples.value.length);
            return;
        }
        notice.value = status === 'limited'
            ? 'That is all the new examples for this rule today. Come back tomorrow.'
            : 'AI examples are not available right now.';
    } catch {
        notice.value = 'Could not ask for more examples. Try again.';
    }
    generating.value = false;
}

async function hide(example: GrammarRuleExample): Promise<void> {
    const before = examples.value;
    examples.value = before.filter((item) => item.id !== example.id);
    try {
        await grammarApi.hideExample(props.ruleId, example.id);
    } catch {
        examples.value = before;
    }
}

onMounted(async () => {
    const status = await load();
    // Someone already asked for examples a moment ago: keep showing progress.
    if (!disposed && (status === 'queued' || status === 'running')) {
        generating.value = true;
        pollStartedAt = Date.now();
        schedulePoll(examples.value.length);
    }
});

onBeforeUnmount(() => {
    disposed = true;
    stopPolling();
});
</script>

<template>
    <div class="space-y-3">
        <p v-if="rows.length === 0 && !generating" class="text-base text-muted-foreground" data-test="examples-empty">
            No examples yet.
        </p>

        <ul v-else class="space-y-3">
            <li
                v-for="{ example, segments } in rows"
                :key="example.id"
                class="rounded-spa border border-border bg-black/10 p-3"
                data-test="example"
            >
                <div class="flex items-start gap-2">
                    <div class="min-w-0 flex-1">
                        <p v-if="example.kind === 'mistake' && example.mistake" class="mb-1 break-words text-sm text-muted-foreground" data-test="example-mistake">
                            <span class="sr-only">Common mistake: </span>
                            <span class="line-through decoration-rose-500/70">{{ example.mistake }}</span>
                        </p>
                        <p class="break-words text-base leading-7 text-fg">
                            <template v-for="(segment, index) in segments" :key="index">
                                <strong v-if="segment.target" class="font-semibold text-primary" data-test="example-target">{{ segment.text }}</strong>
                                <template v-else>{{ segment.text }}</template>
                            </template>
                        </p>
                        <p v-if="example.translation" class="mt-1 break-words text-sm leading-6 text-muted-foreground">{{ example.translation }}</p>
                        <div v-if="example.origin === 'ai' || example.from_content || kindLabels[example.kind ?? ''] || example.kind === 'mistake'" class="mt-2 flex flex-wrap gap-1.5">
                            <UiBadge v-if="example.kind === 'mistake'" tone="danger">Common mistake</UiBadge>
                            <UiBadge v-else-if="kindLabels[example.kind ?? '']">{{ kindLabels[example.kind ?? ''] }}</UiBadge>
                            <UiBadge v-if="example.from_content" tone="primary">From your content</UiBadge>
                            <UiBadge v-if="example.origin === 'ai'">AI</UiBadge>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-col items-center">
                        <SpeakButton v-if="language" :text="example.example" :language="language" />
                        <UiButton
                            v-if="authenticated"
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 text-muted-foreground"
                            title="Hide this example"
                            aria-label="Hide this example"
                            data-test="example-hide"
                            @click="hide(example)"
                        >
                            <EyeOff :size="16" />
                        </UiButton>
                    </div>
                </div>
            </li>
        </ul>

        <div v-if="authenticated" class="space-y-2">
            <UiButton
                variant="secondary"
                size="touch"
                class="w-full sm:w-auto"
                :disabled="generating"
                data-test="examples-more"
                @click="moreExamples"
            >
                <UiSpinner v-if="generating" size="sm" class="mr-2" />
                <Plus v-else :size="16" class="mr-2" />
                {{ generating ? 'Writing examples…' : 'More examples' }}
            </UiButton>
            <p v-if="notice" class="text-sm text-muted-foreground" role="status" data-test="examples-notice">{{ notice }}</p>
        </div>
    </div>
</template>
