<?php

namespace App\Modules\Ai\Interfaces\Http\Controllers;

use App\Contracts\Ai\TextTranslationCapability;
use App\Exceptions\AiClientException;
use App\Http\Controllers\Controller;
use App\Support\AiConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslateController extends Controller
{
    public function __construct(private TextTranslationCapability $translation) {}

    /**
     * On-demand translation of a short learner-facing text (e.g. a saved AI
     * explanation) into the learner's own language.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'target_language' => ['required', 'string', 'regex:/^[a-z]{2}$/'],
        ]);

        if (! AiConfig::isEnabled()) {
            return response()->json(['message' => 'AI feature is disabled.'], 503);
        }

        try {
            $translation = $this->translation->translate($validated['text'], $validated['target_language']);
        } catch (AiClientException $e) {
            return response()->json(['message' => 'AI service unavailable.'], 503);
        }

        return response()->json(['translation' => $translation]);
    }
}
