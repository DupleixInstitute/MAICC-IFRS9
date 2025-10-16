<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class CommunicationCampaign extends Model
{
    use HasFactory;

    protected $fillable = [];
    public $table = "communication_campaigns";
    protected $casts = [
        'selected_days' => 'array',
        'recurring' => 'boolean',
    ];
    public function createdBy()
    {
        return $this->hasOne(User::class, 'id', 'created_by_id');
    }
    public function communicationCampaignBusinessRule()
    {
        return $this->hasOne(CommunicationCampaignBusinessRule::class, 'id', 'communication_campaign_business_rule_id');
    }

    public function branch()
    {
        return $this->hasOne(Branch::class, 'id', 'branch_id');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('id', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('subject', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        });
        $query->when($filters['campaign_type'] ?? null, function ($query, $campaignType) {
            $query->where('campaign_type',  $campaignType);
        });
        $query->when($filters['trigger_type'] ?? null, function ($query, $triggerType) {
            $query->where('trigger_type',  $triggerType);
        });
        $query->when($filters['status'] ?? null, function ($query, $status) {
            $query->where('status',  $status);
        });
    }
}
