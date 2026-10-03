<?php

namespace App\Modules\User\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApiProfileUpdateRequest;
use App\Modules\User\Application\Contracts\LearningStatsReaderInterface;
use App\Modules\User\Interfaces\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private LearningStatsReaderInterface $learningStatsService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => new UserResource($user),
            'today_learned_count' => $this->learningStatsService->getTodayLearnedCount($user->id, $user->timezone),
            'streak_days' => $this->learningStatsService->getStreakDays($user->id, $user->timezone),
        ]);
    }

    public function update(ApiProfileUpdateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $request->user()->update($data);

        return response()->json([
            'message' => 'Profile updated',
            'user' => new UserResource($request->user()->fresh()),
        ]);
    }
}
