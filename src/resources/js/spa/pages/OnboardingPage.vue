<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { CEFR_LEVEL_NAMES, TRANSLATION_LANGUAGE_OPTIONS, useAuthStore, useProfileStore } from '../domains/user';
import { CEFR_LEVELS } from '../domains/content';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiInput from '../shared/ui/UiInput.vue';
import SelectField from '../shared/ui/SelectField.vue';

const router = useRouter();
const authStore = useAuthStore();
const profileStore = useProfileStore();

const STEPS = ['welcome', 'level', 'language', 'goal'] as const;
type Step = (typeof STEPS)[number];

const stepIndex = ref(0);
const currentStep = computed<Step>(() => STEPS[stepIndex.value]);

const firstName = computed(() => (authStore.user?.name ?? '').split(' ')[0] || 'there');

const selectedLevel = ref('');
const selectedLanguage = ref('');
const dailyGoal = ref<number | ''>(10);
const saving = ref(false);

const dailyGoalPresets = [5, 10, 20];

const languageOptions = TRANSLATION_LANGUAGE_OPTIONS.filter((option) => option.value !== '');

function goToCatalog() {
    router.push({ name: 'catalog' });
}

async function continueFromStep() {
    saving.value = true;
    try {
        if (currentStep.value === 'level' && selectedLevel.value) {
            await profileStore.updateCurrentLevel(selectedLevel.value);
        } else if (currentStep.value === 'language' && selectedLanguage.value) {
            await profileStore.updateTranslationLanguage(selectedLanguage.value);
        } else if (currentStep.value === 'goal' && dailyGoal.value !== '') {
            await profileStore.updateDailyGoal(Number(dailyGoal.value));
        }
    } finally {
        saving.value = false;
    }

    if (stepIndex.value < STEPS.length - 1) {
        stepIndex.value += 1;
    } else {
        goToCatalog();
    }
}

function skipStep() {
    if (stepIndex.value < STEPS.length - 1) {
        stepIndex.value += 1;
    } else {
        goToCatalog();
    }
}

function goBack() {
    if (stepIndex.value > 0) {
        stepIndex.value -= 1;
    }
}
</script>

<template>
    <section class="max-w-lg mx-auto mt-12">
        <div class="mb-4 flex items-center justify-between text-sm text-muted-foreground">
            <span>Step {{ stepIndex + 1 }} of {{ STEPS.length }}</span>
            <button type="button" class="hover:underline" @click="goToCatalog">Skip onboarding</button>
        </div>

        <div class="mb-6 flex gap-1.5">
            <span
                v-for="(step, index) in STEPS"
                :key="step"
                class="h-1.5 flex-1 rounded-full"
                :class="index <= stepIndex ? 'bg-primary' : 'bg-muted'"
            />
        </div>

        <UiCard class="space-y-6">
            <template v-if="currentStep === 'welcome'">
                <div class="space-y-2">
                    <h2 class="text-2xl font-semibold">Welcome, {{ firstName }}!</h2>
                    <p class="text-sm text-muted-foreground">
                        A few quick questions help us pick the right difficulty and recommend content that fits you.
                        Takes under a minute — every answer is optional and can be changed later in Settings.
                    </p>
                </div>
                <UiButton variant="primary" class="w-full" @click="continueFromStep">Get started</UiButton>
            </template>

            <template v-else-if="currentStep === 'level'">
                <div class="space-y-2">
                    <h2 class="text-2xl font-semibold">What's your English level?</h2>
                    <p class="text-sm text-muted-foreground">We'll use this to suggest content at the right difficulty.</p>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <button
                        v-for="level in CEFR_LEVELS"
                        :key="level"
                        type="button"
                        class="rounded-md border px-3 py-3 text-left text-sm transition-colors"
                        :class="
                            selectedLevel === level
                                ? 'border-primary bg-primary/10 text-primary'
                                : 'border-border hover:border-primary'
                        "
                        @click="selectedLevel = level"
                    >
                        <div class="font-semibold">{{ level }}</div>
                        <div class="text-xs text-muted-foreground">{{ CEFR_LEVEL_NAMES[level] }}</div>
                    </button>
                </div>

                <div class="flex gap-2">
                    <UiButton variant="ghost" @click="goBack">Back</UiButton>
                    <UiButton variant="ghost" @click="skipStep">Not sure yet</UiButton>
                    <UiButton
                        variant="primary"
                        class="flex-1"
                        :disabled="!selectedLevel || saving"
                        @click="continueFromStep"
                    >
                        {{ saving ? 'Saving...' : 'Continue' }}
                    </UiButton>
                </div>
            </template>

            <template v-else-if="currentStep === 'language'">
                <div class="space-y-2">
                    <h2 class="text-2xl font-semibold">What's your native language?</h2>
                    <p class="text-sm text-muted-foreground">Word translations are shown in this language, when available.</p>
                </div>

                <SelectField v-model="selectedLanguage" label="Native language" placeholder="Choose language" :options="languageOptions" />

                <div class="flex gap-2">
                    <UiButton variant="ghost" @click="goBack">Back</UiButton>
                    <UiButton variant="ghost" @click="skipStep">Skip</UiButton>
                    <UiButton
                        variant="primary"
                        class="flex-1"
                        :disabled="!selectedLanguage || saving"
                        @click="continueFromStep"
                    >
                        {{ saving ? 'Saving...' : 'Continue' }}
                    </UiButton>
                </div>
            </template>

            <template v-else-if="currentStep === 'goal'">
                <div class="space-y-2">
                    <h2 class="text-2xl font-semibold">Set a daily word goal</h2>
                    <p class="text-sm text-muted-foreground">A small, steady goal beats a big one you abandon. You can change this anytime.</p>
                </div>

                <div class="flex gap-2">
                    <button
                        v-for="preset in dailyGoalPresets"
                        :key="preset"
                        type="button"
                        class="rounded-md border px-4 py-2 text-sm transition-colors"
                        :class="
                            dailyGoal === preset
                                ? 'border-primary bg-primary/10 text-primary'
                                : 'border-border hover:border-primary'
                        "
                        @click="dailyGoal = preset"
                    >
                        {{ preset }} words/day
                    </button>
                </div>

                <UiInput v-model.number="dailyGoal" type="number" min="1" max="1000" placeholder="Custom amount" />

                <div class="flex gap-2">
                    <UiButton variant="ghost" @click="goBack">Back</UiButton>
                    <UiButton variant="ghost" @click="skipStep">Skip</UiButton>
                    <UiButton variant="primary" class="flex-1" :disabled="saving" @click="continueFromStep">
                        {{ saving ? 'Saving...' : 'Finish' }}
                    </UiButton>
                </div>
            </template>
        </UiCard>
    </section>
</template>
