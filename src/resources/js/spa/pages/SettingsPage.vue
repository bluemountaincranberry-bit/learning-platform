<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { storeToRefs } from 'pinia';
import UiBadge from '../shared/ui/UiBadge.vue';
import UiButton from '../shared/ui/UiButton.vue';
import UiCard from '../shared/ui/UiCard.vue';
import UiInput from '../shared/ui/UiInput.vue';
import UiSectionHeader from '../shared/ui/UiSectionHeader.vue';
import SelectField from '../shared/ui/SelectField.vue';
import { CEFR_LEVEL_NAMES, TRANSLATION_LANGUAGE_OPTIONS, useProfileStore } from '../domains/user';
import { CEFR_LEVELS } from '../domains/content';
import { learningFlowApi, type LearningFlowPreferences, type LearningFlowResponse } from '../domains/learning';

const translationLanguageOptions = TRANSLATION_LANGUAGE_OPTIONS;

const currentLevelOptions = [
    { value: '', label: 'Not set' },
    ...CEFR_LEVELS.map((level) => ({ value: level, label: `${level} — ${CEFR_LEVEL_NAMES[level]}` })),
];

const aiThoroughnessOptions = [
    { value: '', label: 'Default — as many words as AI finds useful' },
    { value: 'focused', label: 'Fewer, only the most useful words' },
];

const profileStore = useProfileStore();
const { profile } = storeToRefs(profileStore);

const dailyGoalInput = ref<number | ''>('');
const saving = ref(false);
const message = ref('');

const translationLanguageInput = ref('');
const savingTranslationLanguage = ref(false);
const translationLanguageMessage = ref('');

const currentLevelInput = ref('');
const savingCurrentLevel = ref(false);
const currentLevelMessage = ref('');

const aiThoroughnessInput = ref('');
const savingAiThoroughness = ref(false);
const aiThoroughnessMessage = ref('');

const flow = ref<LearningFlowResponse | null>(null);
const flowPreferences = ref<LearningFlowPreferences>({ learning_flow_profile_id: null, session_minutes: null, daily_new_words: null, listening_weight: null, speaking_weight: null, hint_mode: null, difficulty_preference: null });
const flowProfileInput = ref('');
const flowSessionMinutesInput = ref('');
const flowDailyNewWordsInput = ref('');
const flowListeningWeightInput = ref('');
const flowSpeakingWeightInput = ref('');
const flowHintModeInput = ref('');
const flowDifficultyInput = ref('');
const savingFlow = ref(false);
const flowMessage = ref('');

const hintModeOptions = [
    { value: '', label: 'Profile default' },
    { value: 'guided', label: 'More guidance' },
    { value: 'balanced', label: 'Balanced' },
    { value: 'challenge', label: 'Fewer hints' },
];
const difficultyOptions = [
    { value: '', label: 'Profile default' },
    { value: 'easier', label: 'Easier' },
    { value: 'balanced', label: 'Balanced' },
    { value: 'harder', label: 'Harder' },
];

onMounted(async () => {
    await profileStore.fetchProfile();
    dailyGoalInput.value = profile.value?.user.daily_goal ?? '';
    translationLanguageInput.value = profile.value?.user.translation_language ?? '';
    currentLevelInput.value = profile.value?.user.current_level ?? '';
    aiThoroughnessInput.value = profile.value?.user.ai_extraction_thoroughness ?? '';
    try {
        const response = await learningFlowApi.get();
        flow.value = response;
        if (response.preferences) {
            flowPreferences.value = { ...flowPreferences.value, ...response.preferences };
            flowProfileInput.value = response.preferences.learning_flow_profile_id ? String(response.preferences.learning_flow_profile_id) : '';
            flowSessionMinutesInput.value = response.preferences.session_minutes ? String(response.preferences.session_minutes) : '';
            flowDailyNewWordsInput.value = response.preferences.daily_new_words ? String(response.preferences.daily_new_words) : '';
            flowListeningWeightInput.value = response.preferences.listening_weight !== null ? String(response.preferences.listening_weight) : '';
            flowSpeakingWeightInput.value = response.preferences.speaking_weight !== null ? String(response.preferences.speaking_weight) : '';
            flowHintModeInput.value = response.preferences.hint_mode ?? '';
            flowDifficultyInput.value = response.preferences.difficulty_preference ?? '';
        }
    } catch {
        flowMessage.value = 'Failed to load adaptive practice settings.';
    }
});

async function saveFlowPreferences() {
    flowMessage.value = '';
    savingFlow.value = true;
    try {
        await learningFlowApi.update({
            learning_flow_profile_id: flowProfileInput.value ? Number(flowProfileInput.value) : null,
            session_minutes: flowSessionMinutesInput.value ? Number(flowSessionMinutesInput.value) : null,
            daily_new_words: flowDailyNewWordsInput.value ? Number(flowDailyNewWordsInput.value) : null,
            listening_weight: flowListeningWeightInput.value ? Number(flowListeningWeightInput.value) : null,
            speaking_weight: flowSpeakingWeightInput.value ? Number(flowSpeakingWeightInput.value) : null,
            hint_mode: (flowHintModeInput.value || null) as LearningFlowPreferences['hint_mode'],
            difficulty_preference: (flowDifficultyInput.value || null) as LearningFlowPreferences['difficulty_preference'],
        });
        flowMessage.value = 'Saved.';
    } catch {
        flowMessage.value = 'Failed to save.';
    } finally {
        savingFlow.value = false;
    }
}

const dailyGoalNumber = computed(() => {
    const v = dailyGoalInput.value;
    if (v === '' || v === undefined) return null;
    const n = typeof v === 'number' ? v : Number(v);
    return Number.isNaN(n) ? null : n;
});

async function saveDailyGoal() {
    if (dailyGoalNumber.value !== null && (dailyGoalNumber.value < 1 || dailyGoalNumber.value > 1000)) {
        message.value = 'Goal must be between 1 and 1000.';
        return;
    }
    message.value = '';
    saving.value = true;
    try {
        await profileStore.updateDailyGoal(dailyGoalNumber.value ?? null);
        message.value = 'Saved.';
    } catch {
        message.value = 'Failed to save.';
    } finally {
        saving.value = false;
    }
}

async function saveTranslationLanguage() {
    translationLanguageMessage.value = '';
    savingTranslationLanguage.value = true;
    try {
        await profileStore.updateTranslationLanguage(translationLanguageInput.value.trim() || null);
        translationLanguageMessage.value = 'Saved.';
    } catch {
        translationLanguageMessage.value = 'Failed to save.';
    } finally {
        savingTranslationLanguage.value = false;
    }
}

async function saveCurrentLevel() {
    currentLevelMessage.value = '';
    savingCurrentLevel.value = true;
    try {
        await profileStore.updateCurrentLevel(currentLevelInput.value || null);
        currentLevelMessage.value = 'Saved.';
    } catch {
        currentLevelMessage.value = 'Failed to save.';
    } finally {
        savingCurrentLevel.value = false;
    }
}

async function saveAiThoroughness() {
    aiThoroughnessMessage.value = '';
    savingAiThoroughness.value = true;
    try {
        await profileStore.updateAiExtractionThoroughness(aiThoroughnessInput.value || null);
        aiThoroughnessMessage.value = 'Saved.';
    } catch {
        aiThoroughnessMessage.value = 'Failed to save.';
    } finally {
        savingAiThoroughness.value = false;
    }
}
</script>

<template>
    <div class="mx-auto max-w-2xl">
        <UiCard class="space-y-4">
            <UiSectionHeader title="Settings" subtitle="Current learner preferences" />
            <div class="flex flex-wrap gap-2">
                <UiBadge tone="neutral">profile</UiBadge>
                <UiBadge tone="primary">daily goal</UiBadge>
            </div>

            <div class="space-y-4">
                <label class="space-y-2 block">
                    <span class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Daily goal</span>
                    <UiInput
                        id="daily-goal"
                        v-model.number="dailyGoalInput"
                        type="number"
                        min="1"
                        max="1000"
                        placeholder="e.g. 10"
                    />
                </label>

                <p class="text-sm text-muted-foreground">Leave empty to hide the goal.</p>

                <p v-if="message" class="text-sm" :class="message === 'Saved.' ? 'text-success-fg' : 'text-warning'">
                    {{ message }}
                </p>

                <UiButton variant="primary" :disabled="saving" @click="saveDailyGoal">
                    {{ saving ? 'Saving...' : 'Save' }}
                </UiButton>

                <div class="pt-2">
                    <SelectField
                        v-model="translationLanguageInput"
                        label="Native language"
                        placeholder="Choose language"
                        :options="translationLanguageOptions"
                    />
                </div>

                <p class="text-sm text-muted-foreground">Word translations are shown in this language, when available.</p>

                <p v-if="translationLanguageMessage" class="text-sm" :class="translationLanguageMessage === 'Saved.' ? 'text-success-fg' : 'text-warning'">
                    {{ translationLanguageMessage }}
                </p>

                <UiButton variant="primary" :disabled="savingTranslationLanguage" @click="saveTranslationLanguage">
                    {{ savingTranslationLanguage ? 'Saving...' : 'Save' }}
                </UiButton>

                <div class="pt-2">
                    <SelectField
                        v-model="currentLevelInput"
                        label="Your current English level"
                        placeholder="Choose level"
                        :options="currentLevelOptions"
                    />
                </div>

                <p class="text-sm text-muted-foreground">Optional. Helps the AI pick a suitable difficulty when it doesn't already know one for a video.</p>

                <p v-if="currentLevelMessage" class="text-sm" :class="currentLevelMessage === 'Saved.' ? 'text-success-fg' : 'text-warning'">
                    {{ currentLevelMessage }}
                </p>

                <UiButton variant="primary" :disabled="savingCurrentLevel" @click="saveCurrentLevel">
                    {{ savingCurrentLevel ? 'Saving...' : 'Save' }}
                </UiButton>

                <div class="pt-2">
                    <SelectField
                        v-model="aiThoroughnessInput"
                        label="Words extracted from videos you add"
                        placeholder="Choose"
                        :options="aiThoroughnessOptions"
                    />
                </div>

                <p class="text-sm text-muted-foreground">
                    "Fewer" trades word coverage for a shorter, more curated list — useful if the full AI list feels overwhelming.
                </p>

                <p v-if="aiThoroughnessMessage" class="text-sm" :class="aiThoroughnessMessage === 'Saved.' ? 'text-success-fg' : 'text-warning'">
                    {{ aiThoroughnessMessage }}
                </p>

                <UiButton variant="primary" :disabled="savingAiThoroughness" @click="saveAiThoroughness">
                    {{ savingAiThoroughness ? 'Saving...' : 'Save' }}
                </UiButton>

                <div class="mt-6 border-t border-border pt-6 space-y-4">
                    <UiSectionHeader
                        title="Adaptive practice"
                        :subtitle="flow ? `Active flow: ${flow.profile.name} v${flow.profile.version}` : 'Personal learning flow settings'"
                    />
                    <p class="text-sm text-muted-foreground">Choose a recommended learning style. The app still controls the safe sequence and spaced reviews.</p>
                    <SelectField
                        v-if="flow?.available_profiles?.length"
                        v-model="flowProfileInput"
                        label="Learning style"
                        :options="[{ value: '', label: 'Use default' }, ...(flow?.available_profiles ?? []).map((item) => ({ value: String(item.id), label: item.name }))]"
                    />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="space-y-2 block"><span class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Session minutes</span><UiInput v-model="flowSessionMinutesInput" type="number" min="5" max="60" placeholder="Profile default" /></label>
                        <label class="space-y-2 block"><span class="text-xs uppercase tracking-[0.18em] text-muted-foreground">New words per day</span><UiInput v-model="flowDailyNewWordsInput" type="number" min="1" max="30" placeholder="Profile default" /></label>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="space-y-2 block"><span class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Listening share (0–50)</span><UiInput v-model="flowListeningWeightInput" type="number" min="0" max="50" placeholder="Profile default" /></label>
                        <label class="space-y-2 block"><span class="text-xs uppercase tracking-[0.18em] text-muted-foreground">Speaking share (0–50)</span><UiInput v-model="flowSpeakingWeightInput" type="number" min="0" max="50" placeholder="Profile default" /></label>
                    </div>
                    <SelectField v-model="flowHintModeInput" label="Hint mode" :options="hintModeOptions" />
                    <SelectField v-model="flowDifficultyInput" label="Difficulty" :options="difficultyOptions" />
                    <p v-if="flowMessage" class="text-sm" :class="flowMessage === 'Saved.' ? 'text-success-fg' : 'text-warning'">{{ flowMessage }}</p>
                    <UiButton variant="primary" :disabled="savingFlow" @click="saveFlowPreferences">{{ savingFlow ? 'Saving...' : 'Save adaptive settings' }}</UiButton>
                </div>
            </div>
        </UiCard>
    </div>
</template>
