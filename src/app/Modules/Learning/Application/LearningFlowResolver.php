<?php

namespace App\Modules\Learning\Application;

use App\Modules\Learning\Domain\Models\LearningFlowAssignment;
use App\Modules\Learning\Domain\Models\LearningFlowProfile;
use App\Modules\User\Application\Contracts\LearningFlowLearnerReaderInterface;
use Illuminate\Contracts\Auth\Authenticatable;

class LearningFlowResolver
{
    public function __construct(private readonly LearningFlowLearnerReaderInterface $learnerReader) {}

    /** @return array{profile: LearningFlowProfile, config: array<string, mixed>, source: string} */
    public function resolve(Authenticatable $user, ?string $language = null): array
    {
        return $this->resolveForUserId((int) $user->getAuthIdentifier(), $language);
    }

    /** @return array{profile: LearningFlowProfile, config: array<string, mixed>, source: string} */
    public function resolveForUserId(int $userId, ?string $language = null): array
    {
        $learner = $this->learnerReader->forUser($userId);
        $language ??= $learner['translation_language'];
        $now = now();

        $personalAssignment = LearningFlowAssignment::query()
            ->with('profile')
            ->where('user_id', $userId)
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->whereHas('profile', fn ($query) => $query->where('status', 'published'))
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->first();

        if ($personalAssignment?->profile !== null) {
            return $this->resolved($personalAssignment->profile, $learner['preferences'], 'user');
        }

        $preferenceProfileId = $learner['learning_flow_profile_id'];
        $preferenceProfile = $preferenceProfileId === null
            ? null
            : LearningFlowProfile::query()->find($preferenceProfileId);
        if ($preferenceProfile?->status === 'published') {
            return $this->resolved($preferenceProfile, $learner['preferences'], 'learner');
        }

        $assignment = LearningFlowAssignment::query()
            ->with('profile')
            ->whereNull('user_id')
            ->where(function ($query) use ($language): void {
                $query->whereNull('language')->orWhere('language', $language);
            })
            ->where(function ($query) use ($learner): void {
                $query->whereNull('level')->orWhere('level', $learner['current_level']);
            })
            ->where(function ($query) use ($learner): void {
                $query->whereNull('learning_goal')->orWhere('learning_goal', $learner['learning_goal']);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->whereHas('profile', fn ($query) => $query->where('status', 'published'))
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->first();

        if ($assignment?->profile !== null) {
            return $this->resolved($assignment->profile, $learner['preferences'], 'scope');
        }

        $profile = LearningFlowProfile::query()
            ->where('status', 'published')
            ->where('slug', 'balanced')
            ->first();

        if ($profile === null) {
            $profile = new LearningFlowProfile([
                'name' => 'Balanced', 'slug' => 'balanced', 'status' => 'published', 'version' => 1,
                'config' => LearningFlowDefaults::balanced(),
            ]);
        }

        return ['profile' => $profile, 'config' => $this->applyPreferences($learner['preferences'], $this->applyDefaults($profile->config ?? [])), 'source' => 'default'];
    }

    /** @return array{profile: LearningFlowProfile, config: array<string, mixed>, source: string} */
    private function resolved(LearningFlowProfile $profile, array $preferences, string $source): array
    {
        return ['profile' => $profile, 'config' => $this->applyPreferences($preferences, $this->applyDefaults($profile->config ?? [])), 'source' => $source];
    }

    /** @param array<string, mixed> $config */
    private function applyDefaults(array $config): array
    {
        return array_replace_recursive(LearningFlowDefaults::balanced(), $config);
    }

    /** @param array<string, mixed> $config */
    private function applyPreferences(array $preferences, array $config): array
    {
        if ($preferences === []) {
            return $config;
        }

        foreach (['session_minutes', 'daily_new_words'] as $key) {
            if (($preferences[$key] ?? null) !== null) {
                $config[$key] = (int) $preferences[$key];
            }
        }
        if (($preferences['listening_weight'] ?? null) !== null) {
            $config['activity_weights']['listening'] = (int) $preferences['listening_weight'];
        }
        if (($preferences['speaking_weight'] ?? null) !== null) {
            $config['activity_weights']['speaking'] = (int) $preferences['speaking_weight'];
        }
        if (($preferences['hint_mode'] ?? null) !== null) {
            $config['hint_mode'] = $preferences['hint_mode'];
        }
        if (($preferences['difficulty_preference'] ?? null) !== null) {
            $config['difficulty_preference'] = $preferences['difficulty_preference'];
        }

        return $config;
    }
}
