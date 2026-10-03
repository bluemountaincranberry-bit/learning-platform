<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Check, CircleHelp, Lightbulb, Undo2 } from 'lucide-vue-next';
import UiButton from '../../shared/ui/UiButton.vue';
import UiInput from '../../shared/ui/UiInput.vue';

/**
 * Production-recall input for Review/Cloze/Listening cards: either type the
 * word, or tap it together from a jumbled letter bank ("tap-missing-letters").
 * Decoy tiles are drawn from the target word's own visible letters, so this
 * needs no per-language alphabet data and works the same for any script.
 */
const props = withDefaults(
    defineProps<{
        target: string;
        style: 'type' | 'tap-letters';
        busy?: boolean;
    }>(),
    { busy: false },
);

const emit = defineEmits<{
    (e: 'result', payload: { correct: boolean; value: string; hintUsed: boolean; skipped: boolean }): void;
}>();

function normalize(value: string): string {
    return value
        .trim()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '');
}

/** Standard edit-distance (insert/delete/substitute), used to tolerate a single typo instead of marking a near-miss wrong outright. */
function levenshtein(a: string, b: string): number {
    const dp: number[][] = Array.from({ length: a.length + 1 }, () => new Array(b.length + 1).fill(0));
    for (let i = 0; i <= a.length; i++) dp[i][0] = i;
    for (let j = 0; j <= b.length; j++) dp[0][j] = j;
    for (let i = 1; i <= a.length; i++) {
        for (let j = 1; j <= b.length; j++) {
            dp[i][j] = a[i - 1] === b[j - 1] ? dp[i - 1][j - 1] : 1 + Math.min(dp[i - 1][j], dp[i][j - 1], dp[i - 1][j - 1]);
        }
    }
    return dp[a.length][b.length];
}

function shuffle<T>(items: T[]): T[] {
    const copy = [...items];
    for (let i = copy.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [copy[i], copy[j]] = [copy[j], copy[i]];
    }
    return copy;
}

const answered = ref(false);
const isCorrect = ref(false);
const isTypo = ref(false);
const typedValue = ref('');
const hintShown = ref(false);
const hintLevel = ref(0);
const skipped = ref(false);

interface Tile {
    char: string;
    used: boolean;
}

const blankPositions = ref<number[]>([]);
const filled = ref<(string | null)[]>([]);
const tiles = ref<Tile[]>([]);

function resetTapLetters() {
    const chars = Array.from(props.target);
    const letterIdxs = chars.map((c, i) => ({ c, i })).filter(({ c }) => /\S/.test(c));
    // Blank most of the word — leaving only a small fraction visible keeps this
    // a real recall exercise instead of "fill in the last couple of letters".
    const blankCount = Math.min(letterIdxs.length, Math.max(1, Math.round(letterIdxs.length * 0.75)));
    blankPositions.value = shuffle(letterIdxs)
        .slice(0, blankCount)
        .map((x) => x.i)
        .sort((a, b) => a - b);
    filled.value = blankPositions.value.map(() => null);

    const blankChars = blankPositions.value.map((i) => chars[i]);
    const visibleChars = [...new Set(letterIdxs.filter(({ i }) => !blankPositions.value.includes(i)).map(({ c }) => c))];
    const decoys = shuffle(visibleChars).slice(0, Math.min(2, visibleChars.length));
    tiles.value = shuffle([...blankChars, ...decoys]).map((char) => ({ char, used: false }));
}

function reset() {
    answered.value = false;
    isCorrect.value = false;
    isTypo.value = false;
    typedValue.value = '';
    hintShown.value = false;
    hintLevel.value = 0;
    skipped.value = false;
    if (props.style === 'tap-letters') resetTapLetters();
}

watch(() => props.target, reset, { immediate: true });
watch(() => props.style, reset);

function finish(correct: boolean, value: string, typo = false, wasSkipped = false) {
    if (answered.value) return;
    answered.value = true;
    isCorrect.value = correct;
    isTypo.value = typo;
    skipped.value = wasSkipped;
    emit('result', { correct, value, hintUsed: hintShown.value, skipped: wasSkipped });
}

function displayNormalize(value: string): string {
    return value.toLocaleLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
}

const feedbackChars = computed(() => {
    const target = Array.from(displayNormalize(props.target));
    const typed = Array.from(displayNormalize(typedValue.value));
    return target.map((char, index) => {
        const typedChar = typed[index] ?? '';
        if (/\s/.test(char)) return { char, typedChar: '', status: 'space' as const };
        if (!typedChar) return { char, typedChar: '', status: 'empty' as const };
        return { char, typedChar, status: typedChar === char ? 'correct' as const : 'incorrect' as const };
    });
});

const extraTypedChars = computed(() => {
    const targetLength = Array.from(displayNormalize(props.target)).length;
    return Array.from(displayNormalize(typedValue.value)).slice(targetLength);
});

const remainingHintLetters = computed(() => {
    const target = Array.from(displayNormalize(props.target));
    const typed = Array.from(displayNormalize(typedValue.value));
    const start = target.findIndex((char, index) => !/\s/.test(char) && typed[index] !== char);
    if (start < 0) return '';
    return target.slice(start).filter((char) => !/\s/.test(char)).join('');
});

const hintPreview = computed(() => {
    if (props.style === 'tap-letters') {
        const remaining = blankPositions.value
            .map((position, index) => (filled.value[index] === null ? Array.from(props.target)[position] : ''))
            .filter(Boolean)
            .join('');
        return remaining.slice(0, hintLevel.value === 1 ? 1 : 3);
    }
    const lettersToShow = hintLevel.value === 1 ? 1 : 3;
    return remainingHintLetters.value.slice(0, lettersToShow);
});

const canShowMoreHint = computed(() => {
    if (props.style === 'tap-letters') return hintLevel.value < 2 && blankPositions.value.some((_, index) => filled.value[index] === null);
    return hintLevel.value < 2 && remainingHintLetters.value.length > hintLevel.value;
});

function showMoreHint() {
    if (!canShowMoreHint.value) return;
    hintShown.value = true;
    hintLevel.value = hintLevel.value === 0 ? 1 : 2;
}

function skip() {
    finish(false, '', false, true);
}

function submitTyped() {
    if (!typedValue.value.trim()) return;
    const typed = normalize(typedValue.value);
    const target = normalize(props.target);
    if (typed === target) {
        finish(true, typedValue.value);
        return;
    }
    // One-character slip (swap/insert/delete) on a word long enough that a
    // single edit can't have changed it into a different real word — still
    // counts as correct, just flagged, instead of marking a near-miss wrong.
    const closeEnough = target.length >= 4 && levenshtein(typed, target) === 1;
    finish(closeEnough, typedValue.value, closeEnough);
}

const nextBlankSlot = computed(() => filled.value.findIndex((v) => v === null));

function tapTile(index: number) {
    if (answered.value || tiles.value[index].used) return;
    const slot = nextBlankSlot.value;
    if (slot === -1) return;
    filled.value[slot] = tiles.value[index].char;
    tiles.value[index].used = true;
    if (filled.value.every((v) => v !== null)) {
        const chars = Array.from(props.target);
        blankPositions.value.forEach((pos, idx) => {
            chars[pos] = filled.value[idx] as string;
        });
        finish(normalize(chars.join('')) === normalize(props.target), chars.join(''));
    }
}

function undoLastTile() {
    if (answered.value) return;
    for (let slot = filled.value.length - 1; slot >= 0; slot--) {
        if (filled.value[slot] !== null) {
            const char = filled.value[slot];
            filled.value[slot] = null;
            const tile = tiles.value.find((t) => t.used && t.char === char);
            if (tile) tile.used = false;
            return;
        }
    }
}

const displayChars = computed(() =>
    Array.from(props.target).map((c, i) => {
        const blankIdx = blankPositions.value.indexOf(i);
        if (blankIdx === -1) return { char: c, blank: false };
        return { char: filled.value[blankIdx] ?? '', blank: filled.value[blankIdx] === null };
    }),
);
</script>

<template>
    <div class="space-y-3">
        <template v-if="style === 'type'">
            <div class="space-y-3">
                <div class="answer-input">
                    <UiInput
                        v-model="typedValue"
                        :disabled="answered || busy"
                        placeholder="Type what you hear/remember..."
                        autocomplete="off"
                        autocapitalize="off"
                        autocorrect="off"
                        spellcheck="false"
                        @keydown.enter="submitTyped"
                    />
                </div>
            </div>
            <div v-if="typedValue" class="flex max-w-full flex-nowrap items-center justify-start gap-0.5 overflow-x-auto rounded-spa border border-border bg-surface-alt/45 px-3 py-3 font-mono text-lg sm:justify-center" aria-live="polite" aria-label="Answer feedback">
                <template v-for="(item, index) in feedbackChars" :key="index">
                    <span v-if="item.status === 'space'" class="mx-1.5 w-3" aria-hidden="true"></span>
                    <span
                        v-else
                        class="inline-flex min-w-[1.25rem] justify-center rounded px-1"
                        :class="item.status === 'correct' ? 'bg-success-bg text-success-fg' : item.status === 'incorrect' ? 'bg-danger-bg text-danger' : 'text-muted-foreground'"
                    >{{ item.typedChar || '·' }}</span>
                </template>
                <span v-for="(char, index) in extraTypedChars" :key="`extra-${index}`" class="inline-flex min-w-[1.25rem] justify-center rounded bg-danger-bg px-1 text-danger">{{ char }}</span>
            </div>
            <div v-if="hintShown && hintPreview" class="flex flex-wrap items-center justify-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-sm" role="status">
                <Lightbulb :size="16" class="text-primary" aria-hidden="true" />
                <span class="text-muted-foreground">Hint:</span>
                <span class="rounded border border-primary/30 bg-primary/10 px-2 py-0.5 font-mono tracking-wider">
                    <span v-if="style === 'type'" class="text-fg">{{ typedValue || '…' }}</span><span class="font-semibold text-primary">{{ hintPreview }}</span>
                </span>
            </div>
            <p v-else-if="style === 'type'" class="text-center text-xs text-muted-foreground">Type the full answer, then check it.</p>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-center">
                <UiButton class="min-h-11 w-full sm:w-auto" size="sm" variant="primary" :disabled="answered || busy || !typedValue.trim()" @click="submitTyped"><Check :size="16" aria-hidden="true" />Check answer</UiButton>
                <div class="grid grid-cols-1 gap-2 min-[375px]:grid-cols-2 sm:flex">
                    <UiButton class="min-h-11 w-full sm:w-auto" size="sm" variant="secondary" :disabled="answered || busy || !canShowMoreHint" @click="showMoreHint"><Lightbulb :size="16" aria-hidden="true" />{{ hintLevel === 0 ? 'Show 1 letter' : 'Show 2 more' }}</UiButton>
                    <UiButton class="min-h-11 w-full sm:w-auto" size="sm" variant="secondary" :disabled="answered || busy" @click="skip"><CircleHelp :size="16" aria-hidden="true" />I don’t know</UiButton>
                </div>
            </div>
        </template>

        <template v-else>
            <div class="flex flex-wrap justify-center gap-1 font-mono text-2xl tracking-wide">
                <span
                    v-for="(item, i) in displayChars"
                    :key="i"
                    class="inline-flex h-10 min-w-[1.75rem] items-center justify-center border-b-2 sm:h-11 sm:min-w-[2rem]"
                    :class="item.blank ? 'border-primary text-muted-foreground' : 'border-transparent'"
                >{{ item.char === ' ' ? ' ' : item.char || '_' }}</span>
            </div>
            <div class="flex flex-wrap justify-center gap-2">
                <button
                    v-for="(tile, i) in tiles"
                    :key="i"
                    type="button"
                    class="h-11 min-w-[2.75rem] rounded-spa border border-border bg-black/10 px-3 font-mono text-lg font-medium text-fg transition-opacity hover:bg-black/20 disabled:opacity-30 sm:min-w-[3rem]"
                    :disabled="tile.used || answered || busy"
                    @click="tapTile(i)"
                >
                    {{ tile.char }}
                </button>
            </div>
            <div v-if="hintShown && hintPreview" class="flex items-center justify-center gap-2 rounded-lg border border-primary/20 bg-primary/5 px-3 py-2 text-sm" role="status">
                <Lightbulb :size="16" class="text-primary" aria-hidden="true" />
                <span class="text-muted-foreground">Hint:</span>
                <span class="font-mono font-semibold tracking-wider text-primary">{{ hintPreview }}</span>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-center">
                <UiButton class="min-h-11 w-full sm:w-auto" size="sm" variant="primary" :disabled="answered || busy || nextBlankSlot !== -1" @click="finish(normalize(target) === normalize(filled.join('')), filled.join(''))"><Check :size="16" aria-hidden="true" />Check answer</UiButton>
                <div class="grid grid-cols-1 gap-2 min-[375px]:grid-cols-2 sm:flex">
                    <UiButton class="min-h-11 w-full sm:w-auto" size="sm" variant="ghost" :disabled="answered || busy" @click="undoLastTile"><Undo2 :size="16" aria-hidden="true" />Undo</UiButton>
                    <UiButton class="min-h-11 w-full sm:w-auto" size="sm" variant="secondary" :disabled="answered || busy" @click="skip"><CircleHelp :size="16" aria-hidden="true" />I don’t know</UiButton>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.answer-input :deep(input) {
    min-height: 48px;
    font-size: 16px;
}
</style>
