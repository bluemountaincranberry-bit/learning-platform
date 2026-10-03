<?php

namespace App\Modules\Infrastructure\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EntityRevision extends Model
{
    protected $guarded = [];

    public const SOURCE_ADMIN = 'admin';

    public const SOURCE_AI = 'ai';

    protected $casts = ['changes' => 'array'];

    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }
}
