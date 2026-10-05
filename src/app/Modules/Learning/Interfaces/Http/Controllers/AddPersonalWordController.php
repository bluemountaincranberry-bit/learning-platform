<?php

namespace App\Modules\Learning\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddPersonalWordRequest;
use App\Modules\Learning\Application\PersonalWordService;
use Illuminate\Http\JsonResponse;

final class AddPersonalWordController extends Controller
{
    public function __construct(private readonly PersonalWordService $words) {}

    public function __invoke(AddPersonalWordRequest $request): JsonResponse
    {
        $lexeme = $this->words->add(
            (int) $request->user()->id,
            (string) $request->validated('language'),
            (string) $request->validated('lemma'),
        );

        return response()->json([
            'lexeme' => [
                ...$lexeme,
            ],
        ], 201);
    }
}
