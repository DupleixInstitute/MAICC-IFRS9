<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StressScenario extends Model
{
    protected $fillable = [
        'scenario_name', 'reporting_period', 'loan_portfolio_id', 'description',
        's1_pd_mult', 's2_pd_mult', 's3_pd_mult',
        's1_lgd_add', 's2_lgd_add', 's3_lgd_add',
        'result_snapshot', 'saved_by',
    ];

    protected $casts = [
        'result_snapshot' => 'array',
        's1_pd_mult' => 'float', 's2_pd_mult' => 'float', 's3_pd_mult' => 'float',
        's1_lgd_add' => 'float', 's2_lgd_add' => 'float', 's3_lgd_add' => 'float',
    ];
}
