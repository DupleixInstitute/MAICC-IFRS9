<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceMessage extends Model
{
    protected $fillable = ['reporting_period', 'user_id', 'user_name', 'body'];
}
