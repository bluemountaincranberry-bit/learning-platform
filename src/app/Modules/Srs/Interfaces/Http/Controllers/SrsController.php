<?php

namespace App\Modules\Srs\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SrsReviewRequest;
use App\Http\Resources\SrsCardResource;
use App\Modules\Content\Application\Contracts\SrsReviewReferenceReaderInterface;
use App\Modules\Srs\Application\Contracts\SrsRepositoryInterface;
use App\Modules\Srs\Application\Contracts\SrsServiceInterface;
use App\Modules\Srs\Domain\IntervalCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SrsController extends Controller
{
    public function __construct(
        private SrsServiceInterface $srsService,
        private SrsRepositoryInterface $repository,
        private SrsReviewReferenceReaderInterface $reviewReferences,
    ) {}

    public function due(Request $request): JsonResponse
    {
        $items = $this->srsService->getDueCards($request->user()->id);

        return response()->json([
            'items' => SrsCardResource::collection($items),
        ]);
    }

    public function review(SrsReviewRequest $request, IntervalCalculator $calculator): JsonResponse
    {
        $data = $request->validated();
        $card = $this->repository->findCardForUserOrFail((int) $data['card_id'], $request->user()->id);
        $this->authorize('update', $card);
        abort_if($card->lexeme_id === null, 422, 'The reviewed card has no canonical lexeme identity.');

        if (isset($data['content_lexeme_id']) && ! $this->reviewReferences->contentOccurrenceBelongsToUser($request->user()->id, (int) $data['content_lexeme_id'], (int) $card->lexeme_id)) {
            abort(422, 'The content lexeme is not a source for the reviewed card.');
        }
        if (isset($data['transcript_segment_id']) && ! $this->reviewReferences->transcriptSegmentBelongsToUser($request->user()->id, (int) $data['transcript_segment_id'], (int) $card->lexeme_id)) {
            abort(422, 'The transcript segment is not a source for the reviewed card.');
        }

        $card = $this->srsService->reviewCard(
            (int) $data['card_id'],
            (int) $data['grade'],
            $request->user()->id,
            $calculator,
            collect($data)->only(['content_lexeme_id', 'transcript_segment_id', 'exercise_type', 'error_type', 'hint_used', 'answer_metadata'])->all(),
        );

        return response()->json([
            'card' => new SrsCardResource($card),
        ]);
    }
}
