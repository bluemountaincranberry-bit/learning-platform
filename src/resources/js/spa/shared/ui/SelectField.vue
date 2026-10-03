<script setup lang="ts">
import {
    SelectContent,
    SelectItem,
    SelectItemIndicator,
    SelectItemText,
    SelectPortal,
    SelectRoot,
    SelectTrigger,
    SelectValue,
    SelectViewport,
} from 'reka-ui';

type SelectOption = {
    value: string;
    label: string;
};

defineProps<{
    modelValue: string;
    label: string;
    placeholder?: string;
    options: SelectOption[];
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

// reka-ui's SelectItem forbids value="" (it's reserved to mean "clear
// selection"), but several callers model an unset/default choice as
// `{ value: '' }` — so translate '' to this sentinel at the SelectRoot/
// SelectItem boundary and back to '' on the way out, keeping the plain
// string API for everyone using this component.
const EMPTY_VALUE_SENTINEL = '__select-field-empty__';
const toInternal = (value: string) => (value === '' ? EMPTY_VALUE_SENTINEL : value);
const toExternal = (value: string) => (value === EMPTY_VALUE_SENTINEL ? '' : value);
</script>

<template>
    <label class="space-y-2 block">
        <span class="text-xs uppercase tracking-[0.18em] text-muted-foreground">{{ label }}</span>
        <SelectRoot
            :model-value="toInternal(modelValue)"
            @update:model-value="(value) => emit('update:modelValue', toExternal(value ?? ''))"
        >
            <SelectTrigger
                class="flex w-full items-center justify-between gap-3 rounded-md border border-input bg-background px-3 py-2.5 text-left text-sm text-foreground shadow-sm outline-none transition-colors hover:border-primary focus:border-primary data-[placeholder]:text-muted-foreground"
            >
                <!--
                    Not just `<SelectValue :placeholder="..." />`: reka-ui's
                    SelectValue resolves its own display label by looking up
                    the selected value among mounted SelectItem elements —
                    but SelectContent only mounts its items while the popover
                    is open, so a value set programmatically (e.g. loaded
                    from an API after mount) shows the placeholder forever
                    until the user manually opens the dropdown once. Since we
                    already have `options` as a prop here, compute the label
                    ourselves from the external `modelValue` instead of
                    relying on that DOM-dependent lookup.
                -->
                <SelectValue :placeholder="placeholder || label">
                    {{ options.find((option) => option.value === modelValue)?.label ?? (placeholder || label) }}
                </SelectValue>
                <span class="text-muted-foreground">⌄</span>
            </SelectTrigger>

            <SelectPortal>
                <SelectContent
                    class="z-50 overflow-hidden rounded-md border border-border bg-popover text-popover-foreground shadow-[0_18px_50px_rgba(15,23,42,0.12)]"
                    position="popper"
                    :side-offset="6"
                >
                    <SelectViewport class="max-h-72 p-1">
                        <SelectItem
                            v-for="option in options"
                            :key="option.value"
                            :value="toInternal(option.value)"
                            class="relative flex cursor-default select-none items-center rounded-md py-2 pl-9 pr-8 text-sm text-foreground outline-none transition-colors data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-40"
                        >
                            <span class="absolute left-3 inline-flex h-4 w-4 items-center justify-center text-primary">
                                <SelectItemIndicator>✓</SelectItemIndicator>
                            </span>
                            <SelectItemText>{{ option.label }}</SelectItemText>
                        </SelectItem>
                    </SelectViewport>
                </SelectContent>
            </SelectPortal>
        </SelectRoot>
    </label>
</template>
