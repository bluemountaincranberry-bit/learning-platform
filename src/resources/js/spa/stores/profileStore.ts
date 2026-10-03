import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { ProfileResponse } from '../types';
import { profileApi } from '../domains/user/api/profileApi';
import { useAuthStore } from './authStore';

export const useProfileStore = defineStore('profile', () => {
    const profile = ref<ProfileResponse | null>(null);

    async function fetchProfile(): Promise<ProfileResponse | null> {
        const auth = useAuthStore();
        if (!auth.isAuthenticated) {
            profile.value = null;
            return null;
        }
        try {
            const data = await profileApi.getProfile();
            profile.value = data;
            return data;
        } catch {
            profile.value = null;
            return null;
        }
    }

    async function updateDailyGoal(value: number | null): Promise<void> {
        const data = await profileApi.updateProfile({ daily_goal: value });
        if (profile.value) {
            profile.value = { ...profile.value, user: data.user };
        }
    }

    async function updateTranslationLanguage(value: string | null): Promise<void> {
        const data = await profileApi.updateProfile({ translation_language: value });
        if (profile.value) {
            profile.value = { ...profile.value, user: data.user };
        }
    }

    async function updateCurrentLevel(value: string | null): Promise<void> {
        const data = await profileApi.updateProfile({ current_level: value });
        if (profile.value) {
            profile.value = { ...profile.value, user: data.user };
        }
    }

    async function updateAiExtractionThoroughness(value: string | null): Promise<void> {
        const data = await profileApi.updateProfile({ ai_extraction_thoroughness: value });
        if (profile.value) {
            profile.value = { ...profile.value, user: data.user };
        }
    }

    function clearProfile(): void {
        profile.value = null;
    }

    return {
        profile,
        fetchProfile,
        updateDailyGoal,
        updateTranslationLanguage,
        updateCurrentLevel,
        updateAiExtractionThoroughness,
        clearProfile,
    };
});
