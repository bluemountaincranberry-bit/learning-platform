<?php

namespace App\Modules\Srs\Domain\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SrsCard extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'next_review_at' => 'datetime',
            'ease_factor' => 'float',
        ];
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(SrsReview::class);
    }
}
