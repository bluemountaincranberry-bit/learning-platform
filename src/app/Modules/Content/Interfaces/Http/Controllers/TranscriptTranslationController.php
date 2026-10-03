<?php

namespace App\Modules\Content\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Content\Interfaces\Jobs\TranslateTranscriptSegmentsJob;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Application\Transcript\TranscriptTranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TranscriptTranslationController extends Controller
{
    public function __construct(private readonly TranscriptTranslationService $service) {}

    public function index(Request $request, Content $content): JsonResponse
    {
        abort_unless(Gate::allows('view', $content), 404);

        $language = $request->user()->translation_language
            ?? config('ai.analysis.translation_language', 'ru');

        $cached = $this->service->getCached($content, $language);
        if ($this->service->hasMissing($content, $language)) {
            TranslateTranscriptSegmentsJob::dispatch($content->id, $language);

            return response()->json([
                'status' => 'pending',
                'language' => $language,
                'translations' => $cached,
            ], 202);
        }

        return response()->json([
            'status' => 'ready',
            'language' => $language,
            'translations' => $cached,
        ]);
    }
}
