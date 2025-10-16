<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LoanApplicationLinkedApprovalStage extends Model
{
    use LogsActivity, HasFactory;

    protected $table = 'loan_applications_linked_approval_stages';
    protected $casts = [
        'is_current' => 'boolean',
        'completed' => 'boolean',
    ];
    protected $appends = ['was_sent_back', 'has_same_role_as_approver'];
    protected $fillable = [
        'is_current',
    ];


    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'loan_application_id');
    }

    public function stage()
    {
        return $this->belongsTo(LoanApprovalStage::class, 'loan_approval_stage_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('id', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        });
        $query->when($filters['loan_category_id'] ?? null, function ($query, $loan_category_id) {
            $query->where('loan_category_id', $loan_category_id);
        });
        $query->when($filters['status'] ?? null, function ($query, $status) {
            $query->where('status', $status);
        });
        $query->when($filters['staff_id'] ?? null, function ($query, $staff_id) {
            $query->where('staff_id', $staff_id);
        });
        $query->when($filters['branch_id'] ?? null, function ($query, $branch_id) {
            $query->where('branch_id', $branch_id);
        });
        $query->when($filters['province_id'] ?? null, function ($query, $province_id) {
            $query->where('province_id', $province_id);
        });
        $query->when($filters['district_id'] ?? null, function ($query, $district_id) {
            $query->where('district_id', $district_id);
        });
        $query->when($filters['ward_id'] ?? null, function ($query, $ward_id) {
            $query->where('ward_id', $ward_id);
        });
        $query->when($filters['village_id'] ?? null, function ($query, $village_id) {
            $query->where('village_id', $village_id);
        });
        $query->when($filters['date_range'] ?? null, function ($query, $search) {
            $date = explode(' to ', $search);
            if (!empty($date[1])) {
                $query->whereBetween('date', [$date[0], $date[1]]);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    public function getWasSentBackAttribute()
    {
        $associatedHistory = LoanApplicationApprovalStageHistory::where('stage_id', $this->id)->first();
        if ($associatedHistory) {
            if ($associatedHistory->action == 'Reassign') {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    //the role of the auntenticated user and comprate with the role of the approver
    public function getHasSameRoleAsApproverAttribute()
{
    $userRoles = auth()->user()->roles->pluck('id')->toArray();
    $approvalRoles = $this->approver?->roles->pluck('id')->toArray();

    if (empty($userRoles) || empty($approvalRoles)) {
        return false;
    }

    return !empty(array_intersect($userRoles, $approvalRoles));
}

}
