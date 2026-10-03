<?php

namespace App\Modules\Ai\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromptTemplate extends Model
{
    protected $guarded = [];

    public function versions(): HasMany
    {
        return $this->hasMany(PromptTemplateVersion::class)->orderBy('version');
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(PromptTemplateVersion::class, 'active_version_id');
    }
}
