<?php

namespace App\Modules\Content\Interfaces\Http\Controllers;

use App\Exceptions\AiClientException;
use App\Http\Controllers\Controller;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Application\Transcript\TranscriptLexemeLookupService;
use App\Support\AiConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TranscriptController extends Controller
{
    public function __construct(private readonly TranscriptLexemeLookupService $lexemeLookupService) {}

    public function index(Request $request, Content $content): JsonResponse
    {
        abort_unless(Gate::allows('view', $content), 404);

        $query = $content->transcriptSegments()->with('lexemes');
        if ($request->filled('from_ms')) {
            $query->where('end_ms', '>=', max(0, (int) $request->integer('from_ms')));
        }
        if ($request->filled('to_ms')) {
            $query->where('start_ms', '<=', max(0, (int) $request->integer('to_ms')));
        }

        $limit = min(max((int) $request->integer('limit', 500), 1), 1000);
        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $segments = $rows->map(static function ($segment): array {
            return [
                'id' => $segment->id,
                'sequence' => $segment->sequence,
                'start_ms' => $segment->start_ms,
                'end_ms' => $segment->end_ms,
                'text' => $segment->text,
                'language' => $segment->language,
                'source' => $segment->source,
                'lexemes' => $segment->lexemes->map(static function ($lexeme): array {
                    return [
                        'content_lexeme_id' => $lexeme->id,
                        'text' => $lexeme->text,
                        'start_offset' => $lexeme->pivot->start_offset,
                        'end_offset' => $lexeme->pivot->end_offset,
                        'surface_text' => $lexeme->pivot->surface_text,
                        'match_type' => $lexeme->pivot->match_type,
                    ];
                })->values()->all(),
            ];
        })->values();

        return response()->json([
            'content_id' => $content->id,
            'language' => $content->language,
            'segments' => $segments,
            'has_more' => $hasMore,
            'next_from_ms' => $hasMore ? $rows->last()?->start_ms : null,
        ]);
    }

    /**
     * A learner tapped a transcript word that isn't linked to a ContentLexeme
     * yet (see TranscriptViewer.vue — every word is clickable, not just
     * pre-matched ones). Creates/reuses the ContentLexeme, links it to this
     * exact occurrence, and returns its translation so the word panel can
     * show a result immediately.
     */
    public function createLexeme(Request $request, Content $content): JsonResponse
    {
        abort_unless(Gate::allows('view', $content), 404);

        if (! AiConfig::isEnabled()) {
            return response()->json(['message' => 'AI feature is disabled.'], 503);
        }

        $validated = $request->validate([
            'transcript_segment_id' => ['required', 'integer'],
            'text' => ['required', 'string', 'max:191'],
            'start_offset' => ['required', 'integer', 'min:0'],
            'end_offset' => ['required', 'integer', 'gt:start_offset'],
        ]);

        $segment = $content->transcriptSegments()->findOrFail($validated['transcript_segment_id']);
        $nativeLanguage = $request->user()->translation_language ?? config('ai.analysis.translation_language', 'ru');

        try {
            $result = $this->lexemeLookupService->lookup(
                $content,
                $segment,
                $validated['text'],
                (int) $validated['start_offset'],
                (int) $validated['end_offset'],
                $nativeLanguage,
            );
        } catch (AiClientException $e) {
            return response()->json(['message' => 'AI service unavailable.'], 503);
        }

        return response()->json($result);
    }
}
