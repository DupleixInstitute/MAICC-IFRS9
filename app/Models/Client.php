<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Jetstream\Features;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Client extends Model
{
    use LogsActivity, HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'name',
        'phone',
        'email',
        'trading_name',
        'external_id',
        'id_number',
        'gender',
        'dob',
        'profile_photo_path',
        'province_id',
        'district_id',
        'ward_id',
        'village_id',
        'branch_id',
        'created_by_id',
        'country_id',
        'registration_country_id',
        'main_bank_id',
        'second_bank_id',
        'third_bank_id',
        'industry_type_id',
        'legal_type_id',
        'mobile'
    ];

    protected $appends = [
        'profile_photo_url',
        'age',
        'join_date',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id', 'id');
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    public function village()
    {
        return $this->belongsTo(Village::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }  public function registrationCountry()
    {
        return $this->belongsTo(Country::class,'registration_country_id');
    }

    public function mainBank()
    {
        return $this->belongsTo(Bank::class, 'main_bank_id');
    }

    public function secondBank()
    {
        return $this->belongsTo(Bank::class, 'second_bank_id');
    }

    public function thirdBank()
    {
        return $this->belongsTo(Bank::class, 'third_bank_id');
    }

    public function industryType()
    {
        return $this->belongsTo(IndustryType::class);
    }

    public function legalType()
    {
        return $this->belongsTo(LegalType::class);
    }
    public function ratio()
    {
        return $this->hasOne(RatioAnalysis::class,'client_id','id');
    }

    public function porter()
    {
        return $this->hasOne(PorterFiveForcesAnalysis::class, 'client_id', 'id');
    }

    public function loans()
    {
        return $this->hasMany(LoanApplication::class, 'member_id', 'id');
    }

    public function shareholders()
    {
        return $this->hasMany(Shareholder::class, 'client_id', 'id');
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class, 'patient_id', 'id');
    }

    public function files()
    {
        return $this->hasMany(File::class, 'record_id', 'id')->where('category', 'patients');
    }

    public function sales()
    {
        return $this->hasMany(InventoryProductSale::class);
    }


    public function getJoinDateAttribute()
    {
        return $this->created_at->format('Y-m-d');
    }

    public function getAgeAttribute()
    {
        return Carbon::now()->diffForHumans(Carbon::parse($this->dob), true, true);
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
                    ->orWhere('id_number', 'like', '%' . $search . '%')
                    ->orWhere('trading_name', 'like', '%' . $search . '%')
                    ->orWhere('external_id', 'like', '%' . $search . '%');
            });
        })->when($filters['gender'] ?? null, function ($query, $gender) {
            $query->where(function ($query) use ($gender) {
                $query->where('gender', $gender);
            });
        })->when($filters['province_id'] ?? null, function ($query, $gender) {
            $query->where(function ($query) use ($gender) {
                $query->where('province_id', $gender);
            });
        })->when($filters['branch_id'] ?? null, function ($query, $gender) {
            $query->where(function ($query) use ($gender) {
                $query->where('branch_id', $gender);
            });
        })->when($filters['district_id'] ?? null, function ($query, $gender) {
            $query->where(function ($query) use ($gender) {
                $query->where('district_id', $gender);
            });
        })->when($filters['ward_id'] ?? null, function ($query, $gender) {
            $query->where(function ($query) use ($gender) {
                $query->where('ward_id', $gender);
            });
        })->when($filters['village_id'] ?? null, function ($query, $gender) {
            $query->where(function ($query) use ($gender) {
                $query->where('village_id', $gender);
            });
        });
    }

    public function getProfilePhotoUrlAttribute()
    {
        return $this->profile_photo_path
            ? Storage::disk($this->profilePhotoDisk())->url($this->profile_photo_path)
            : $this->defaultProfilePhotoUrl();
    }

    /**
     * Get the default profile photo URL if no profile photo has been uploaded.
     *
     * @return string
     */
    protected function defaultProfilePhotoUrl()
    {
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=7F9CF5&background=EBF4FF';
    }

    protected function profilePhotoDisk()
    {
        return isset($_ENV['VAPOR_ARTIFACT_NAME']) ? 's3' : config('jetstream.profile_photo_disk', 'public');
    }

    public function updateProfilePhoto(UploadedFile $photo)
    {
        tap($this->profile_photo_path, function ($previous) use ($photo) {
            $this->forceFill([
                'profile_photo_path' => $photo->storePublicly(
                    'profile-photos', ['disk' => $this->profilePhotoDisk()]
                ),
            ])->save();

            if ($previous) {
                Storage::disk($this->profilePhotoDisk())->delete($previous);
            }
        });
    }

    /**
     * Delete the user's profile photo.
     *
     * @return void
     */
    public function deleteProfilePhoto()
    {
        if (!Features::managesProfilePhotos()) {
            return;
        }

        Storage::disk($this->profilePhotoDisk())->delete($this->profile_photo_path);

        $this->forceFill([
            'profile_photo_path' => null,
        ])->save();
    }

    public function getTotalBalanceAttribute()
    {
        return Invoice::where('patient_id', $this->id)->sum();
    }

    public function isDemoPatient()
    {
        return $this->email === 'patient@localhost.com';
    }
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

}
