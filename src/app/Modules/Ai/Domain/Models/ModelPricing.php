<?php

namespace App\Modules\Ai\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class ModelPricing extends Model
{
    protected $table = 'model_pricing';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'prompt_per_1k_usd' => 'float',
            'completion_per_1k_usd' => 'float',
        ];
    }
}
