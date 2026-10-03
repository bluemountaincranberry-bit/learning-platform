<?php

namespace App\Modules\Content\Application\Data;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\Lexeme;

final class LexemeMetadataOptions
{
    /** @return array<int, string> */
    public static function cefrLevels(): array
    {
        return Content::CEFR_LEVELS;
    }

    /** @return array<string, string> */
    public static function partsOfSpeech(): array
    {
        return Lexeme::PARTS_OF_SPEECH;
    }
}
