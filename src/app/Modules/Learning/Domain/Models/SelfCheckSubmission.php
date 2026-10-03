<?php

namespace App\Modules\Learning\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class SelfCheckSubmission extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['result' => 'array'];
    }
}
