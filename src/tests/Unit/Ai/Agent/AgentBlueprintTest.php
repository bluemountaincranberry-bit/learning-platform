<?php

use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentBlueprint;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;

/**
 * Minimal AgentTool implementation, just so AgentBlueprint has a real
 * class-string implementing the interface to validate against.
 */
class BlueprintTestFakeTool implements AgentTool
{
    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition('fake', 'fake', [], AgentToolDefinition::SIDE_EFFECT_READ_ONLY);
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        return [];
    }
}

function validBlueprintArgs(): array
{
    return [
        'name' => 'test_agent',
        'systemPrompt' => 'You are a test agent.',
        'tools' => [BlueprintTestFakeTool::class],
        'maxIterations' => 6,
        'allowedSideEffects' => [AgentToolDefinition::SIDE_EFFECT_READ_ONLY],
    ];
}

test('constructs successfully with valid arguments', function () {
    $blueprint = new AgentBlueprint(...validBlueprintArgs());

    expect($blueprint->name)->toBe('test_agent')
        ->and($blueprint->tools)->toBe([BlueprintTestFakeTool::class])
        ->and($blueprint->maxIterations)->toBe(6)
        ->and($blueprint->allowedSideEffects)->toBe([AgentToolDefinition::SIDE_EFFECT_READ_ONLY]);
});

test('rejects an empty name', function () {
    new AgentBlueprint(...[...validBlueprintArgs(), 'name' => '  ']);
})->throws(InvalidArgumentException::class, 'non-empty name');

test('rejects an empty system prompt', function () {
    new AgentBlueprint(...[...validBlueprintArgs(), 'systemPrompt' => '']);
})->throws(InvalidArgumentException::class, 'non-empty systemPrompt');

test('rejects an empty tools list', function () {
    new AgentBlueprint(...[...validBlueprintArgs(), 'tools' => []]);
})->throws(InvalidArgumentException::class, 'at least one tool');

test('rejects a tool class-string that does not implement AgentTool', function () {
    new AgentBlueprint(...[...validBlueprintArgs(), 'tools' => [stdClass::class]]);
})->throws(InvalidArgumentException::class, 'must be a class-string implementing');

test('rejects a maxIterations below 1', function () {
    new AgentBlueprint(...[...validBlueprintArgs(), 'maxIterations' => 0]);
})->throws(InvalidArgumentException::class, 'maxIterations >= 1');

test('rejects an empty allowedSideEffects list', function () {
    new AgentBlueprint(...[...validBlueprintArgs(), 'allowedSideEffects' => []]);
})->throws(InvalidArgumentException::class, 'at least one allowed sideEffect');

test('rejects an unknown sideEffect value in allowedSideEffects', function () {
    new AgentBlueprint(...[...validBlueprintArgs(), 'allowedSideEffects' => ['not-a-real-level']]);
})->throws(InvalidArgumentException::class, 'unknown sideEffect');
