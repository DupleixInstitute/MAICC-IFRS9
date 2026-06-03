<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SicrTrigger extends Model
{
    protected $table = 'finance_sicr_triggers';

    protected $fillable = [
        'group_id',
        'item_id',
        'account_number',
        'reason',
        'attachment_path',
        'triggered_by',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(SicrGroup::class, 'group_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SicrItem::class, 'item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
