<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StageingRule extends Model
{
    protected $table = 'finance_stageing_rules';

    protected $fillable = [
        'institution_type',
        'stage_1_threshold',
        'stage_3_threshold',
    ];
}
