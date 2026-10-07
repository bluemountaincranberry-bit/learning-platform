<?php

namespace App\Modules\Learning\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Learning\Application\PersonalWordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PersonalWordLearningController extends Controller
{
    public function __construct(private readonly PersonalWordService $words) {}

    public function start(Request $request, int $lexeme): JsonResponse
    {
        $this->words->startLearning((int) $request->user()->id, $lexeme);

        return response()->json(['ok' => true]);
    }

    public function stop(Request $request, int $lexeme): JsonResponse
    {
        $this->words->stopLearning((int) $request->user()->id, $lexeme);

        return response()->json(['ok' => true]);
    }

    public function markKnown(Request $request, int $lexeme): JsonResponse
    {
        $this->words->markKnown((int) $request->user()->id, $lexeme);

        return response()->json(['ok' => true]);
    }

    public function unmarkKnown(Request $request, int $lexeme): JsonResponse
    {
        $this->words->unmarkKnown((int) $request->user()->id, $lexeme);

        return response()->json(['ok' => true]);
    }
}
