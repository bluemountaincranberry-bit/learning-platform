<?php

namespace App\Modules\Ai\Interfaces\Jobs;

use App\Modules\Ai\Application\Agent\Contracts\AgentService;
use App\Contracts\Ai\AiErrorMessage;
use App\Modules\Ai\Domain\Models\AgentConversation;
use App\Modules\Ai\Domain\Models\AgentMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class RunAgentTurnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public int $conversationId) {}

    public function tags(): array
    {
        return ['agent-conversation:'.$this->conversationId, 'job:run-agent-turn'];
    }

    public function handle(): void
    {
        try {
            $conversation = AgentConversation::query()->findOrFail($this->conversationId);
            $this->resolveAgent($conversation->agent_type)->handleTurn($this->conversationId);
        } catch (Throwable $e) {
            Log::error('RunAgentTurnJob failed', ['conversation_id' => $this->conversationId, 'message' => AiErrorMessage::safe($e)]);
            AgentConversation::query()->find($this->conversationId)?->messages()->create([
                'role' => AgentMessage::ROLE_ASSISTANT,
                'content' => 'Something went wrong while processing your request. Please try again.',
            ]);
        }
    }

    private function resolveAgent(string $agentType): AgentService
    {
        $agentClass = config('ai.agent.registry', [])[$agentType] ?? null;
        if ($agentClass === null) {
            throw new RuntimeException("No agent registered for agent_type \"{$agentType}\" (see config('ai.agent.registry')).");
        }

        return app($agentClass);
    }
}
