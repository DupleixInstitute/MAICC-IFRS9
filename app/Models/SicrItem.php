<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One qualitative signal on the SICR list. stage_implied is the stage a
 * trigger on it moves a loan to: 2 = significant increase in credit risk,
 * 3 = credit-impaired (IFRS 9 Appendix A).
 */
class SicrItem extends Model
{
    protected $table = 'finance_sicr_items';

    protected $fillable = [
        'group_id',
        'name',
        'description',
        'stage_implied',
        'evidence_required',
        'standard_reference',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'evidence_required' => 'boolean',
        'stage_implied' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(SicrGroup::class, 'group_id');
    }
}
