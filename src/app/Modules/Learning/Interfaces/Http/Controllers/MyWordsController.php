<?php

namespace App\Modules\Learning\Interfaces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MyWordsIndexRequest;
use App\Modules\Learning\Application\MyWordsService;
use App\Modules\Learning\Interfaces\Http\Resources\MyWordResource;
use Illuminate\Http\JsonResponse;

class MyWordsController extends Controller
{
    public function __construct(
        private MyWordsService $myWordsService
    ) {}

    public function index(MyWordsIndexRequest $request): JsonResponse
    {
        $paginator = $this->myWordsService->getPaginated(
            $request->user()->id,
            $request->validated()
        );

        return MyWordResource::collection($paginator)->response();
    }
}
