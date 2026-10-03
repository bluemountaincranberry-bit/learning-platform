<?php

namespace App\Modules\Infrastructure\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class EventLog extends Model
{
    public $timestamps = false;

    protected $table = 'event_log';

    protected $fillable = ['topic', 'partition', 'offset', 'key', 'payload'];

    protected function casts(): array
    {
        return ['partition' => 'integer', 'offset' => 'integer'];
    }
}
