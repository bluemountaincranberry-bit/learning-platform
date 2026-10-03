<?php

namespace App\Modules\Learning\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LearningFlowPreferencesRequest;
use App\Modules\Learning\Application\LearningFlowResolver;
use App\Modules\Learning\Domain\Models\LearningFlowProfile;
use App\Modules\User\Application\Contracts\LearningFlowPreferenceStoreInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningFlowController extends Controller
{
    public function __construct(
        private readonly LearningFlowResolver $resolver,
        private readonly LearningFlowPreferenceStoreInterface $preferenceStore,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $resolved = $this->resolver->resolve($request->user());

        return response()->json([
            'profile' => [
                'id' => $resolved['profile']->id,
                'name' => $resolved['profile']->name,
                'slug' => $resolved['profile']->slug,
                'version' => $resolved['profile']->version,
                'source' => $resolved['source'],
            ],
            'config' => $resolved['config'],
            'preferences' => $this->preferenceStore->forUser((int) $request->user()->getAuthIdentifier()),
            'available_profiles' => LearningFlowProfile::query()->where('status', 'published')->orderBy('id')->get(['id', 'name', 'slug', 'description', 'version']),
        ]);
    }

    public function update(LearningFlowPreferencesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        if (($validated['learning_flow_profile_id'] ?? null) !== null) {
            abort_unless(LearningFlowProfile::query()->whereKey($validated['learning_flow_profile_id'])->where('status', 'published')->exists(), 422, 'This learning flow is not available.');
        }

        $preferences = $this->preferenceStore->updateForUser((int) $request->user()->getAuthIdentifier(), $validated);

        return response()->json(['preferences' => $preferences, 'flow' => $this->resolver->resolve($request->user())]);
    }
}
