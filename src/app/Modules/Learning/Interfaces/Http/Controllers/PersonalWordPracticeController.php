<?php

namespace App\Modules\Learning\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PersonalWordPracticeRequest;
use App\Modules\Learning\Application\PersonalWordService;
use Illuminate\Http\JsonResponse;

final class PersonalWordPracticeController extends Controller
{
    public function __construct(private readonly PersonalWordService $words) {}

    public function __invoke(PersonalWordPracticeRequest $request, int $lexeme): JsonResponse
    {
        $data = $request->validated();
        $scheduled = $this->words->practice(
            (int) $request->user()->id,
            $lexeme,
            (string) $data['dimension'],
            (bool) $data['correct'],
            (bool) ($data['hint_used'] ?? false),
        );

        return response()->json(['ok' => true, 'review_scheduled' => $scheduled]);
    }
}
