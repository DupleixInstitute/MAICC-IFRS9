<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunicationCampaignLog extends Model
{
    protected $fillable = [];
    public $table = "communication_campaign_logs";
    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('id', 'like', '%' . $search . '%');
            });
        });
    }
    public function createdBy()
    {
        return $this->hasOne(User::class, 'id', 'created_by_id');
    }

    public function campaign()
    {
        return $this->hasOne(CommunicationCampaign::class, 'id', 'communication_campaign_id');
    }
}
