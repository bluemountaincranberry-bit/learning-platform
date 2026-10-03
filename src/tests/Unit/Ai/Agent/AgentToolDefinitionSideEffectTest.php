<?php

use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;

test('constructing a definition requires a known sideEffect value', function () {
    new AgentToolDefinition('x', 'x', ['type' => 'object', 'properties' => []], 'not-a-real-level');
})->throws(RuntimeException::class, 'unknown sideEffect');

test('assertSideEffectsAllowed passes when every tool is within the allowed set', function () {
    $definitions = [
        new AgentToolDefinition('a', 'a', [], AgentToolDefinition::SIDE_EFFECT_READ_ONLY),
        new AgentToolDefinition('b', 'b', [], AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY),
    ];

    AgentToolDefinition::assertSideEffectsAllowed(
        $definitions,
        [AgentToolDefinition::SIDE_EFFECT_READ_ONLY, AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY]
    );
})->throwsNoExceptions();

test('assertSideEffectsAllowed throws — failing wiring at boot — when a tool exceeds the allowed level', function () {
    $definitions = [
        new AgentToolDefinition('a', 'a', [], AgentToolDefinition::SIDE_EFFECT_READ_ONLY),
        new AgentToolDefinition('publish_to_catalog', 'writes live catalog', [], AgentToolDefinition::SIDE_EFFECT_PUBLISH),
    ];

    AgentToolDefinition::assertSideEffectsAllowed(
        $definitions,
        [AgentToolDefinition::SIDE_EFFECT_READ_ONLY, AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY]
    );
})->throws(RuntimeException::class, 'publish_to_catalog');
