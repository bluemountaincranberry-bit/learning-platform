<?php

namespace App\Modules\Learning\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Learning\Application\Data\StatsLearner;
use App\Modules\Learning\Application\ProgressStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressStatsController extends Controller
{
    public function __construct(
        private ProgressStatsService $progressStatsService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json($this->progressStatsService->getStats(new StatsLearner(
            $user->id,
            $user->timezone,
            $user->daily_goal,
        )));
    }
}
