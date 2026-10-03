<script setup lang="ts">
import UiInput from '../../shared/ui/UiInput.vue';

const name = defineModel<string>('name', { required: true });
const description = defineModel<string>('description', { required: true });
const model = defineModel<string>('model', { required: true });
</script>

<template>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="mb-1 block text-xs font-medium text-muted-foreground">Name</label>
            <UiInput v-model="name" placeholder="Human-readable name" />
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-muted-foreground">Model override (optional)</label>
            <UiInput v-model="model" placeholder="e.g. gpt-4o-mini" />
        </div>
    </div>
    <div class="mt-3">
        <label class="mb-1 block text-xs font-medium text-muted-foreground">Description</label>
        <UiInput v-model="description" placeholder="What is this prompt for?" />
    </div>
</template>
