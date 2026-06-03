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
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SicrItem::class, 'group_id');
    }
}
