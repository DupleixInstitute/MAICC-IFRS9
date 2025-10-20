<?php

namespace App\Models;

use App\Models\LoanPortfolio;
use App\Models\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LoanBook extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'contract_id',
        'external_identity_id',
        'customer_id',
        'portfolio_group',
        'reporting_year',
        'reporting_month',
        'reporting_period',
        'create_date',
        'value_date',
        'tenor',
        'overdue_days',
        'interest_rate',
        'principal_balance',
        'disbursed',
        'repayments',
        'collateral_type',
        'expected_loss_provision',
        'overdue_status',
        'is_month_end',
        'client_id',
        'loan_portfolio_id',
        'contract_status',
        'ifrs9stage_pre_qualitative',
        'ifrs9stage_postalitative',
        'sicr',
        'ifrs9_stage_prequalitative',
        'sicr_trigger',
        'ifrs9_stage_postqualitative',
        'ecl_value',
        'pd_value',
        'lgd_value',
        'customer_lgd',
        'collection_lgd',
        '12_pd',
        'lifetime_pd',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'create_date' => 'date',
        'due_date' => 'date',
        'principal_balance' => 'decimal:2',
        'expected_loss_provision' => 'decimal:2',
        'overdue_days' => 'integer',
        'reporting_year' => 'integer',
        'reporting_month' => 'integer',
        'is_month_end' => 'boolean',
        'ifrs9_stage_prequalitative' => 'integer',
        'sicr_trigger' => 'integer',
        'ifrs9_stage_postqualitative' => 'integer',
    ];

    /**
     * Get the client that owns the loan book entry.
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'external_identity_id', 'customer_id');
    }

    /**
     * Scope a query to only include month-end records.
     */
    public function scopeMonthEnd($query)
    {
        return $query->where('is_month_end', true);
    }

    /**
     * Scope a query to filter by reporting period.
     */
    public function scopeByPeriod($query, $year, $month)
    {
        return $query->where([
            'reporting_year' => $year,
            'reporting_month' => $month
        ]);
    }

    /**
     * Scope a query to filter by overdue status.
     */
    public function scopeByOverdueStatus($query, $status)
    {
        return $query->where('overdue_status', $status);
    }
    public function portfolio()
    {
        return $this->belongsTo(LoanPortfolio::class, 'portfolio_group');
    }
}
