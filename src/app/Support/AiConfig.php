<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

class AiConfig
{
    /**
     * Whether AI features (explain lexeme, suggest CEFR, etc.) are enabled.
     * When false, API returns 403/503 so SPA can hide or disable the "Explain" button.
     */
    public static function isEnabled(): bool
    {
        return (bool) Config::get('ai.enabled', false);
    }

    /**
     * Whether the content-authoring chat agent is enabled. Requires the
     * OpenAI provider (tool calling is not implemented for Ollama).
     */
    public static function isAgentEnabled(): bool
    {
        return (bool) Config::get('ai.agent.enabled', false)
            && Config::get('ai.provider', 'openai') === 'openai';
    }
}
