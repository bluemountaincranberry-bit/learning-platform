<?php

namespace App\Modules\Infrastructure\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EventConsumption extends Model
{
    public $timestamps = false;
    protected $primaryKey = null;
    public $incrementing = false;
    protected $guarded = [];
    protected $casts = ['consumed_at' => 'datetime', 'version' => 'integer'];
}
