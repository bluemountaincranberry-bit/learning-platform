<?php

namespace App\Modules\Content\Application\Data;

class ContentProcessingRequestResult
{
    public function __construct(
        public bool $accepted,
        public ?string $reason = null,
    ) {}

    public static function accepted(): self
    {
        return new self(true);
    }

    public static function blocked(string $reason): self
    {
        return new self(false, $reason);
    }
}
