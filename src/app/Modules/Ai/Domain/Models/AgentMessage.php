<?php

namespace App\Modules\Ai\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMessage extends Model
{
    protected $guarded = [];

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_TOOL = 'tool';

    protected function casts(): array
    {
        return [
            'tool_args' => 'array',
            'tool_result' => 'array',
            'voice_audio_pinned' => 'boolean',
            'voice_audio_expires_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AgentConversation::class, 'agent_conversation_id');
    }
}
