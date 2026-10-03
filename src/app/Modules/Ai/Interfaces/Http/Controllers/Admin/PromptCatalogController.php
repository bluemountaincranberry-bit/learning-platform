<?php

namespace App\Modules\Ai\Interfaces\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Application\PromptCatalogService;
use Illuminate\Http\JsonResponse;

class PromptCatalogController extends Controller
{
    public function __construct(private readonly PromptCatalogService $service) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->service->flows()]);
    }
}
