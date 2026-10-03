<?php

namespace App\Modules\Ai\Application\Agent\Data;

/**
 * One tool invocation requested by the model in a single turn. `arguments`
 * is the model's raw decoded JSON — never trust it directly, tools must
 * validate against their own schema before acting on it.
 */
final class AgentToolCall
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments,
    ) {}
}
