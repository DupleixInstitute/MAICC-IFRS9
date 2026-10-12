<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SicrGroup extends Model
{
    protected $table = 'finance_sicr_groups';

    protected $fillable = [
        'name',
        'description',
        'sort_order',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SicrItem::class, 'group_id');
    }
}
