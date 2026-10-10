<script setup lang="ts">
import { computed } from 'vue';
import { DropdownMenuContent, DropdownMenuItem, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuTrigger } from 'reka-ui';
import { ArrowLeft, Check, Dumbbell, Ellipsis, MessageCircle, Plus, Sparkles, Undo2 } from 'lucide-vue-next';
import UiBadge from '../../shared/ui/UiBadge.vue';
import UiButton from '../../shared/ui/UiButton.vue';
import type { GrammarRule } from '../../types';

/**
 * Rule page header (VIK-41): back · title · level on one line, one primary
 * action that follows the learner's state (Add to my grammar → Practice),
 * everything else in the ⋯ menu. Emits intents; the page does the work.
 */
const props = withDefaults(defineProps<{
    rule: GrammarRule;
    authenticated?: boolean;
    /** A my-grammar request is in flight. */
    busy?: boolean;
}>(), {
    authenticated: false,
    busy: false,
});

const emit = defineEmits<{
    back: [];
    add: [];
    practice: [];
    learned: [];
    remove: [];
    discuss: [];
    edit: [];
}>();

type MenuAction = 'discuss' | 'edit' | 'learned' | 'remove';

const canAdd = computed(() => props.authenticated && !props.rule.in_my_list && !props.rule.learned);

const menu = computed(() => {
    const items: { action: MenuAction; label: string; icon: typeof Check }[] = [];
    if (props.authenticated) items.push({ action: 'edit', label: 'Edit with AI', icon: Sparkles });
    items.push({ action: 'discuss', label: 'Discuss with AI', icon: MessageCircle });
    if (props.authenticated && !props.rule.learned) {
        items.push({ action: 'learned', label: 'Mark as learned', icon: Check });
    }
    if (props.authenticated && (props.rule.in_my_list || props.rule.learned)) {
        items.push({ action: 'remove', label: 'Remove from my grammar', icon: Undo2 });
    }
    return items;
});

function select(action: MenuAction): void {
    if (action === 'discuss') emit('discuss');
    else if (action === 'edit') emit('edit');
    else if (action === 'learned') emit('learned');
    else emit('remove');
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center gap-1" data-test="rule-title-row">
            <UiButton
                variant="ghost"
                size="icon-touch"
                class="-ml-3 shrink-0"
                aria-label="Back"
                title="Back"
                data-test="rule-back"
                @click="emit('back')"
            >
                <ArrowLeft :size="20" />
            </UiButton>
            <h2 class="min-w-0 truncate text-xl font-semibold text-fg" :title="rule.title">{{ rule.title }}</h2>
            <div class="flex shrink-0 items-center gap-1.5 pl-1">
                <UiBadge v-if="rule.level" tone="neutral">{{ rule.level }}</UiBadge>
                <UiBadge v-if="authenticated && rule.learned" tone="success">Learned</UiBadge>
            </div>
            <DropdownMenuRoot>
                <DropdownMenuTrigger
                    class="-mr-2 ml-auto inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    aria-label="More actions"
                    data-test="rule-menu"
                >
                    <Ellipsis :size="20" />
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent
                        class="z-50 min-w-[220px] overflow-hidden rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-[0_18px_50px_rgba(15,23,42,0.12)]"
                        align="end"
                        :side-offset="6"
                    >
                        <DropdownMenuItem
                            v-for="item in menu"
                            :key="item.action"
                            class="flex min-h-11 cursor-default select-none items-center gap-2 rounded-md px-3 py-2 text-sm text-foreground outline-none transition-colors data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-40"
                            :disabled="busy && item.action !== 'discuss'"
                            data-test="rule-menu-item"
                            @select="select(item.action)"
                        >
                            <component :is="item.icon" :size="16" />
                            {{ item.label }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenuRoot>
        </div>

        <UiButton
            variant="primary"
            size="touch"
            class="w-full sm:w-auto sm:px-6"
            :disabled="busy && canAdd"
            data-test="rule-primary"
            @click="canAdd ? emit('add') : emit('practice')"
        >
            <template v-if="canAdd"><Plus :size="16" /> Add to my grammar</template>
            <template v-else><Dumbbell :size="16" /> Practice</template>
        </UiButton>
    </div>
</template>
