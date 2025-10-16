<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Laravel\Jetstream\HasProfilePhoto;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\LogsActivity;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use HasRoles;
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'gender',
        'mobile',
        'email',
        'password',
        'country_id',
        'title_id',
        'tel',
        'zip',
        'external_id',
        'practice_number',
        'address',
        'active',
        'profile_photo_path',
        'description',
        'qualifications',
        'branch_id',
        'slack_webhook_url',
        'telegram_user_id',
        'member_id',
        'group_email',
        'can_reassign'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'active' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'profile_photo_url',
        'current_role',
        'can',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }


    public function getCanAttribute()
    {
        return $this->roles->first() ? $this->roles->first()->permissions->pluck('name') : [];
    }

    public function getCurrentRoleAttribute()
    {

        return $this->roles->first()->name ?? null;
    }

    public function widgets()
    {
        return $this->belongsTo(UserWidgets::class);
    }

    public function patients()
    {
        return $this->hasMany(Client::class, 'doctor_id', 'id');
    }

    public function sales()
    {
        return $this->hasMany(InventoryProductSale::class, 'created_by_id', 'id');
    }

    public function scopeOrderByName($query)
    {
        $query->orderBy('last_name')->orderBy('first_name');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('id', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('mobile', 'like', '%' . $search . '%')
                    ->orWhere('external_id', 'like', '%' . $search . '%');
            });
        })->when($filters['role'] ?? null, function ($query, $role) {
            $query->whereHas('roles', function ($query) use ($role) {
                $query->where('role_id', $role);
            });
        })->when($filters['gender'] ?? null, function ($query, $gender) {
            $query->where(function ($query) use ($gender) {
                $query->where('gender', $gender);
            });
        });
    }

    public function isDemoUser()
    {
        return $this->email === 'admin@localhost.com' || $this->email === 'patient@localhost.com';
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }


}
