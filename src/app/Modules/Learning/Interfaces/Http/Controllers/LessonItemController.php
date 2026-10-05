<?php

namespace App\Modules\Learning\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Learning\Application\LessonItemService;
use App\Modules\Learning\Application\LessonGrammarSelectionService;
use App\Modules\Learning\Domain\Models\Lesson;
use App\Modules\Learning\Domain\Models\LessonCorrection;
use App\Modules\Learning\Domain\Models\LessonGrammarCandidate;
use App\Modules\Learning\Domain\Models\LessonLexemeCandidate;
use App\Modules\Learning\Interfaces\Http\Requests\LessonCorrectionRequest;
use App\Modules\Learning\Interfaces\Http\Requests\LessonGrammarRequest;
use App\Modules\Learning\Interfaces\Http\Requests\LessonLexemeRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LessonItemController extends Controller
{
    public function __construct(private readonly LessonItemService $items) {}

    public function storeLexeme(LessonLexemeRequest $request, Lesson $lesson): JsonResponse
    {
        $this->authorize('update', $lesson);

        return response()->json($this->lexeme($this->items->createLexeme($lesson, $request->validated()), $lesson), 201);
    }

    public function updateLexeme(LessonLexemeRequest $request, Lesson $lesson, int $item): JsonResponse
    {
        $this->authorize('update', $lesson);

        return response()->json($this->lexeme($this->items->updateLexeme($lesson, $item, $request->validated()), $lesson));
    }

    public function deleteLexeme(Lesson $lesson, int $item): Response
    {
        $this->authorize('update', $lesson);
        $this->items->deleteLexeme($lesson, $item);

        return response()->noContent();
    }

    public function restoreLexeme(Lesson $lesson, int $item): JsonResponse
    {
        $this->authorize('update', $lesson);

        return response()->json($this->lexeme($this->items->restoreLexeme($lesson, $item), $lesson));
    }

    public function storeGrammar(LessonGrammarRequest $request, Lesson $lesson): JsonResponse
    {
        $this->authorize('update', $lesson);

        return response()->json($this->grammar($this->items->createGrammar($lesson, $request->validated())), 201);
    }

    public function updateGrammar(LessonGrammarRequest $request, Lesson $lesson, int $item): JsonResponse
    {
        $this->authorize('update', $lesson);

        return response()->json($this->grammar($this->items->updateGrammar($lesson, $item, $request->validated())));
    }

    public function deleteGrammar(Lesson $lesson, int $item): Response
    {
        $this->authorize('update', $lesson);
        $this->items->deleteGrammar($lesson, $item);

        return response()->noContent();
    }

    public function restoreGrammar(Lesson $lesson, int $item): JsonResponse
    {
        $this->authorize('update', $lesson);

        return response()->json($this->grammar($this->items->restoreGrammar($lesson, $item)));
    }

    public function addGrammarToMyGrammar(Request $request, Lesson $lesson, int $item, LessonGrammarSelectionService $selection): JsonResponse
    {
        $this->authorize('update', $lesson);

        return response()->json($selection->addToMyGrammar($lesson, $item, (int) $request->user()->id));
    }

    public function storeCorrection(LessonCorrectionRequest $request, Lesson $lesson): JsonResponse
    {
        $this->authorize('update', $lesson);

        return response()->json($this->correction($this->items->createCorrection($lesson, $request->validated()), $lesson), 201);
    }

    public function updateCorrection(LessonCorrectionRequest $request, Lesson $lesson, int $item): JsonResponse
    {
        $this->authorize('update', $lesson);

        return response()->json($this->correction($this->items->updateCorrection($lesson, $item, $request->validated()), $lesson));
    }

    public function deleteCorrection(Lesson $lesson, int $item): Response
    {
        $this->authorize('update', $lesson);
        $this->items->deleteCorrection($lesson, $item);

        return response()->noContent();
    }

    public function restoreCorrection(Lesson $lesson, int $item): JsonResponse
    {
        $this->authorize('update', $lesson);

        return response()->json($this->correction($this->items->restoreCorrection($lesson, $item), $lesson));
    }

    /** @return array<string, mixed> */
    private function lexeme(LessonLexemeCandidate $item, Lesson $lesson): array
    {
        return [
            'id' => $item->id, 'text' => $item->text, 'type' => $item->type, 'level' => $item->level,
            'translation' => $item->translation, 'example' => $item->example,
            'example_translation' => $item->example_translation, 'status' => $item->status,
            'matched_lexeme_id' => $item->matched_lexeme_id, 'source' => $item->source, 'language' => $lesson->language,
        ];
    }

    /** @return array<string, mixed> */
    private function grammar(LessonGrammarCandidate $item): array
    {
        return [
            'id' => $item->id, 'title' => $item->title, 'summary' => $item->summary, 'body' => $item->body,
            'example' => $item->example, 'example_translation' => $item->example_translation,
            'status' => $item->status, 'matched_grammar_rule_id' => $item->matched_grammar_rule_id,
            'personal_grammar_rule_id' => $item->personal_grammar_rule_id,
            'source' => $item->source,
        ];
    }

    /** @return array<string, mixed> */
    private function correction(LessonCorrection $item, Lesson $lesson): array
    {
        return [
            'id' => $item->id, 'original_text' => $item->original_text, 'corrected_text' => $item->corrected_text,
            'explanation' => $item->explanation, 'source' => $item->source, 'lesson_id' => $lesson->id,
        ];
    }
}
