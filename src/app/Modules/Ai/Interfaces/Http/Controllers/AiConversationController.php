<?php

namespace App\Modules\Ai\Interfaces\Http\Controllers;

use App\Exceptions\AiClientException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendChatMessageRequest;
use App\Modules\Ai\Application\AiConversationService;
use App\Contracts\Ai\ChatAiServiceInterface;
use App\Modules\Ai\Domain\Models\AiConversation;
use App\Modules\Ai\Domain\Models\AiMessage;
use App\Support\AiConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiConversationController extends Controller
{
    public function __construct(
        private ChatAiServiceInterface $chatAiService,
        private AiConversationService $aiConversationService
    ) {}

    public function store(Request $request): JsonResponse
    {
        if (! AiConfig::isEnabled()) {
            return response()->json(['message' => 'AI feature is disabled.'], 503);
        }

        $conversation = $this->aiConversationService->createForUser($request->user()->id);

        return response()->json([
            'conversation_id' => $conversation->id,
        ], 201);
    }

    public function storeMessage(SendChatMessageRequest $request, AiConversation $conversation): JsonResponse
    {
        if (! AiConfig::isEnabled()) {
            return response()->json(['message' => 'AI feature is disabled.'], 503);
        }

        if ($conversation->user_id !== $request->user()->id) {
            abort(404);
        }

        $content = $request->validated('content');

        $history = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (AiMessage $m) => ['role' => $m->role, 'content' => $m->content])
            ->all();

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $content,
        ]);

        try {
            $replyText = $this->chatAiService->reply($history, $content);
        } catch (AiClientException $e) {
            return response()->json(['message' => 'AI service unavailable.'], 503);
        }

        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $replyText,
        ]);

        return response()->json([
            'reply' => $assistantMessage->content,
            'message_id' => $assistantMessage->id,
        ]);
    }
}
