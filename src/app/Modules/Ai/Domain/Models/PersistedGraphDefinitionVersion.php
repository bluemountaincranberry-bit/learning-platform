<?php

namespace App\Modules\Ai\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersistedGraphDefinitionVersion extends Model
{
    protected $table = 'graph_definition_versions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nodes' => 'array',
            'edges' => 'array',
        ];
    }

    public function graphDefinition(): BelongsTo
    {
        return $this->belongsTo(PersistedGraphDefinition::class, 'graph_definition_id');
    }
}
