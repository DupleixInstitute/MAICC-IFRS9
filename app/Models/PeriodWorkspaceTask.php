<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodWorkspaceTask extends Model
{
    protected $fillable = [
        'reporting_period', 'task_key', 'status', 'completed_by', 'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}
