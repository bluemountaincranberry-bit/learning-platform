<?php

namespace App\Modules\Ai\Interfaces\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Domain\Models\AgentGraphRun;
use App\Modules\Ai\Application\Agent\Graph\GraphRunStatusStreamer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Live "which node is running now" for the graph-builder canvas — same
 * `response()->stream()` + `text/event-stream` shape
 * `TutorConversationController::storeMessage()` already uses for chat,
 * just polling `GraphRunStatusStreamer` instead of forwarding an agent
 * loop's own deltas.
 */
class GraphRunStreamController extends Controller
{
    public function __construct(private readonly GraphRunStatusStreamer $streamer) {}

    public function stream(AgentGraphRun $run): StreamedResponse
    {
        return response()->stream(
            fn () => $this->streamer->stream($run->id, fn (array $payload) => $this->emitSse($payload)),
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emitSse(array $payload): void
    {
        echo 'data: '.json_encode($payload)."\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}
