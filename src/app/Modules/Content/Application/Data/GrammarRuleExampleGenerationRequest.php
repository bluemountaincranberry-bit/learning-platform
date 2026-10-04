<?php

namespace App\Modules\Content\Application\Data;

/**
 * Outcome of asking for an AI batch of rule examples.
 * `queued` and `active` mean examples are on the way; `limited` (daily
 * batches used up) and `unavailable` (AI disabled) mean they are not.
 */
final readonly class GrammarRuleExampleGenerationRequest
{
    public const QUEUED = 'queued';

    public const ACTIVE = 'active';

    public const LIMITED = 'limited';

    public const UNAVAILABLE = 'unavailable';

    public function __construct(public string $status) {}

    public function isPending(): bool
    {
        return in_array($this->status, [self::QUEUED, self::ACTIVE], true);
    }
}
