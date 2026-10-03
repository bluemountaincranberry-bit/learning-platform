<script setup lang="ts">
import { Search } from 'lucide-vue-next';
import UiButton from '../../shared/ui/UiButton.vue';
import UiCard from '../../shared/ui/UiCard.vue';
import UiInput from '../../shared/ui/UiInput.vue';
import UiSectionHeader from '../../shared/ui/UiSectionHeader.vue';
import SelectField from '../../shared/ui/SelectField.vue';
import type { MyWordStatus } from '../../domains/learning';

defineProps<{
    status: MyWordStatus;
    search: string;
    level: string;
}>();

const emit = defineEmits<{
    'update:status': [value: MyWordStatus];
    'update:search': [value: string];
    'update:level': [value: string];
}>();

const statusTabs: { value: MyWordStatus; label: string }[] = [
    { value: 'in_learning', label: 'In learning' },
    { value: 'known', label: 'Known' },
    { value: 'new', label: 'New' },
    { value: 'all', label: 'All' },
];

const levelOptions = [
    { value: 'all', label: 'All' },
    { value: 'A1', label: 'A1' },
    { value: 'A2', label: 'A2' },
    { value: 'B1', label: 'B1' },
    { value: 'B2', label: 'B2' },
    { value: 'C1', label: 'C1' },
    { value: 'C2', label: 'C2' },
];
</script>

<template>
    <UiCard class="space-y-4">
        <UiSectionHeader title="My words" subtitle="Manage what you practice, know, and want to add from content." />

        <div class="flex flex-wrap gap-2">
            <UiButton
                v-for="tab in statusTabs"
                :key="tab.value"
                size="sm"
                :variant="status === tab.value ? 'primary' : 'secondary'"
                @click="emit('update:status', tab.value)"
            >
                {{ tab.label }}
            </UiButton>
        </div>

        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem]">
            <label class="space-y-2">
                <span class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Search</span>
                <div class="relative">
                    <Search :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                    <UiInput :model-value="search" class="pl-9" placeholder="Word or translation" @update:modelValue="emit('update:search', String($event))" />
                </div>
            </label>

            <SelectField :model-value="level" label="Level" placeholder="Choose level" :options="levelOptions" @update:modelValue="emit('update:level', String($event))" />
        </div>
    </UiCard>
</template>
