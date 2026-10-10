<?php

namespace App\Modules\Interview\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class InterviewMilestone extends Model
{
    protected $guarded = [];

    protected $casts = ['target_date' => 'date:Y-m-d'];
}
