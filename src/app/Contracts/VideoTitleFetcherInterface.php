<?php

namespace App\Contracts;

interface VideoTitleFetcherInterface
{
    /**
     * Best-effort lookup of a human-readable title for a source URL.
     *
     * Returns null (never throws) when the URL is unsupported or the
     * lookup fails, so callers can treat this as optional UX sugar.
     */
    public function fetch(string $sourceUrl): ?string;
}
