<?php

namespace App\Modules\Content\Application\Contracts;

interface TranscriptLexemeLinkerInterface
{
    public function linkForContent(int $contentId): void;
}
