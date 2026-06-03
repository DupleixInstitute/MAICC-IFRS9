<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditLossData extends Model
{
    use HasFactory;
    protected $table = 'macro_credit_loss_data';

     protected $fillable = [
        'portfolio_id',
        'definition_id',
        'period',
        'value',
        'created_by',
        'source',
        'notes'
    ];

    public function definition()
    {
        return $this->belongsTo(CreditLossDefinition::class, 'definition_id');
    }

    // Relationships
    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(LoanPortfolio::class, 'portfolio_id');
    }

    public function scenarioProfile(): BelongsTo
    {
        return $this->belongsTo(ScenarioProfiles::class);
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenarios::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}