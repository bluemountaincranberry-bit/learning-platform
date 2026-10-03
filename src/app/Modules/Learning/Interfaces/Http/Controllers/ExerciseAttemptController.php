<?php

namespace App\Modules\Learning\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ExerciseAttemptRequest;
use App\Modules\Learning\Application\ExerciseAttemptService;
use Illuminate\Http\JsonResponse;
use App\Modules\Learning\Domain\Models\ExerciseAttempt;

class ExerciseAttemptController extends Controller
{
    public function __construct(private readonly ExerciseAttemptService $service) {}

    public function store(ExerciseAttemptRequest $request): JsonResponse
    {
        $attempt = $this->service->create($request->user(), $request->validated(), $request->file('audio'));
        return response()->json(['attempt' => [
            'id' => $attempt->id, 'status' => $attempt->status, 'exercise_type' => $attempt->exercise_type,
            'user_text' => $attempt->user_text, 'score' => $attempt->score, 'is_correct' => $attempt->is_correct,
            'error_type' => $attempt->error_type, 'provider_result' => $attempt->provider_result,
        ]], 202);
    }

    public function show(ExerciseAttempt $exerciseAttempt): JsonResponse
    {
        abort_unless($exerciseAttempt->user_id === request()->user()->id, 404);

        return response()->json(['attempt' => [
            'id' => $exerciseAttempt->id, 'status' => $exerciseAttempt->status,
            'exercise_type' => $exerciseAttempt->exercise_type, 'user_text' => $exerciseAttempt->user_text,
            'score' => $exerciseAttempt->score, 'is_correct' => $exerciseAttempt->is_correct,
            'error_type' => $exerciseAttempt->error_type, 'provider_result' => $exerciseAttempt->provider_result,
        ]]);
    }
}
