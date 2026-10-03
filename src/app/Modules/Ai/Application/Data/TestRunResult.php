<?php

namespace App\Modules\Ai\Application\Data;

/**
 * Result of `PromptTemplateAdminService::testRun()` — carries the rendered
 * system/user text back alongside the model's response so the admin can
 * see exactly what was sent, not just what came back.
 */
final readonly class TestRunResult
{
    public function __construct(
        public string $renderedSystem,
        public string $renderedUser,
        public string $response,
    ) {}
}
