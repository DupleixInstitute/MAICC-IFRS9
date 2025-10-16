<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunicationCampaignAttachmentType extends Model
{
    protected $fillable = [];
    public $table="communication_campaign_attachment_types";
    public $timestamps=false;
    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('id', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%');
            });
        });
    }
}
