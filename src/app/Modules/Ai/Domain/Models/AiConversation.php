<?php

namespace App\Modules\Ai\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    use HasFactory;

    protected static function newFactory(): \Database\Factories\AiConversationFactory
    {
        return \Database\Factories\AiConversationFactory::new();
    }

    protected $table = 'ai_conversations';

    protected $guarded = [];

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id');
    }
}
