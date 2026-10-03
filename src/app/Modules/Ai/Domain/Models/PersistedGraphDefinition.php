<?php

namespace App\Modules\Ai\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersistedGraphDefinition extends Model
{
    protected $table = 'graph_definitions';

    protected $guarded = [];

    public function versions(): HasMany
    {
        return $this->hasMany(PersistedGraphDefinitionVersion::class, 'graph_definition_id')->orderBy('version');
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(PersistedGraphDefinitionVersion::class, 'active_version_id');
    }
}
