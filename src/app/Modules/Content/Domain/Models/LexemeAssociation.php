<?php

namespace App\Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LexemeAssociation extends Model
{
    protected $guarded = [];

    public const TYPE_SYNONYM = 'synonym';

    public const TYPE_NEAR_SYNONYM = 'near_synonym';

    public const TYPE_ANTONYM = 'antonym';

    public const TYPE_COGNATE = 'cognate';

    public const TYPE_WORD_FAMILY = 'word_family';

    public const TYPE_PHRASAL_VERB = 'phrasal_verb';

    public const TYPE_COLLOCATION = 'collocation';

    public const TYPE_GRAMMATICAL = 'grammatical';

    public const TYPE_THEMATIC = 'thematic';

    public const TYPE_HOMOGRAPH = 'homograph';

    public const TYPE_RELATED = 'related';

    public const TYPES = [
        self::TYPE_SYNONYM, self::TYPE_NEAR_SYNONYM, self::TYPE_ANTONYM,
        self::TYPE_COGNATE, self::TYPE_WORD_FAMILY, self::TYPE_PHRASAL_VERB,
        self::TYPE_COLLOCATION, self::TYPE_GRAMMATICAL, self::TYPE_THEMATIC,
        self::TYPE_HOMOGRAPH, self::TYPE_RELATED,
    ];

    public function lexeme(): BelongsTo
    {
        return $this->belongsTo(Lexeme::class);
    }

    public function relatedLexeme(): BelongsTo
    {
        return $this->belongsTo(Lexeme::class, 'related_lexeme_id');
    }
}
