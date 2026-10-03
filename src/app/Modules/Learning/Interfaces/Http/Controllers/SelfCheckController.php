<?php

namespace App\Modules\Learning\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SelfCheckStartRequest;
use App\Http\Requests\Api\SelfCheckSubmitRequest;
use App\Modules\Content\Application\Contracts\ContentViewAuthorizationInterface;
use App\Modules\Learning\Application\SelfCheckService;
use Illuminate\Http\JsonResponse;

class SelfCheckController extends Controller
{
    public function __construct(
        private SelfCheckService $selfCheckService,
        private ContentViewAuthorizationInterface $contentAuthorization,
    ) {}

    public function start(SelfCheckStartRequest $request): JsonResponse
    {
        $data = $request->validated();
        $contentId = (int) $data['content_id'];
        $this->contentAuthorization->assertCanView($request->user(), $contentId);

        $items = $this->selfCheckService->getItemsForStart(
            $contentId,
            (int) $request->user()->getAuthIdentifier(),
            (int) ($data['limit'] ?? 10)
        );

        return response()->json(['items' => $items]);
    }

    public function submit(SelfCheckSubmitRequest $request): JsonResponse
    {
        $data = $request->validated();
        $contentId = (int) $data['content_id'];
        $this->contentAuthorization->assertCanView($request->user(), $contentId);

        $result = $this->selfCheckService->computeScore($contentId, (int) $request->user()->getAuthIdentifier(), $data['answers'], $data['operation_id'] ?? null);

        return response()->json($result);
    }
}
