<?php

namespace App\Modules\Ai\Interfaces\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Application\Agent\Graph\GraphNodeRegistry;
use Illuminate\Http\JsonResponse;

class NodePaletteController extends Controller
{
    public function __construct(private readonly GraphNodeRegistry $nodeRegistry) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->nodeRegistry->describeAll()]);
    }
}
