<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\LoanPortfolio;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LossGivenDefaultCummulative extends Model
{
    use HasFactory;
    protected $table = 'loss_given_default_cummulative';

    protected $fillable = [
        'id',
        'lgd_calculation_level',
        'lgd_calculation_id',    
        'lgd_calculation_code',
        'reporting_period',
        'start_period',
        'loss_given_default_id',
        'start_total_stage3',
        'end_total_stage3',
        'lgd_cummulative',
        'lgd_cummulative_percent',
        'cure_rate_cummulative',
        'cured_amount',
        'cure_amount_stage1',
        'cure_amount_stage2',
        'recovery_rate_cummulative',
        'recovered_amount',
        'partially_recovered_amount',
        'fully_recovered_amount',
        'total_disbursments',
        'periods_count',
        'periods_list',
        'calculation_source',
        'is_active_or_closed',
        'supporting_file',
        'created_at',
        'updated_at'
    ];

     protected $casts = [
        'reporting_period' => 'date',
        'start_period' => 'date',
        'last_reporting_period' => 'date',
        'is_active_or_closed' => 'string',
        'calculation_source' => 'string',
        'lgd_calculation_level' => 'string',
        'periods_count' => 'integer',
        'periods_list' => 'array',
    ];

    /**
     * Get the portfolio group associated with the loss given default.
     *
     * @return BelongsTo
     */
    public function portfolioGroup()
         {
        return $this->belongsTo(LoanPortfolio::class, 'lgd_calculation_id', 'id');
        }

    public function sector(){
        return $this->belongsTo(IndustryType::class, 'lgd_calculation_code', 'code');
    }
        public function supportingDocuments()
    {
        return $this->morphMany(
            SupportingDocument::class,
            'documentable'
        );
    }

    
    /**
     * Get the main supporting document
     */
    public function supportingDocument()
    {
        return $this->supportingDocuments()->latest()->first();
    }

    protected $appends = ['has_supporting_document'];

    public function getHasSupportingDocumentAttribute()
    {
        return $this->supportingDocuments()->exists();
    }
}
