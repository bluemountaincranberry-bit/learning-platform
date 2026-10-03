<script setup lang="ts">
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import PageState from '../components/ui/PageState.vue';
import { useCategories } from '../domains/content';
import UiCard from '../shared/ui/UiCard.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';

const router = useRouter();
const { loading, error, categories, loadCategories } = useCategories();

onMounted(loadCategories);
</script>

<template>
    <UiCard class="space-y-4">
        <UiSectionHeader title="Content categories" subtitle="Browse the catalog by broad lanes" />
        <PageState :loading="loading" :error="error">
            <div class="grid gap-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4" aria-label="Content categories">
                <button
                    v-for="item in categories"
                    :key="item"
                    type="button"
                    class="rounded-spa border border-border bg-black/10 px-3 py-2 text-left text-sm capitalize text-fg-secondary transition-colors hover:border-primary hover:text-fg"
                    @click="router.push({ name: 'catalog' })"
                >
                    {{ item }}
                </button>
            </div>
        </PageState>
    </UiCard>
</template>
