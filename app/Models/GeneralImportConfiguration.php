<?php

namespace App\Models;

use App\Models\User;
use App\Models\GeneralImportTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralImportConfiguration extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'is_reporting_period',
        'is_portfolio_group_id',
        'template_id',
        'template_column_position',
        'column_description',
        'column_data_type',
        'minimum_value',
        'maximum_value',
        'active_status',
        // 'create_date',
        'update_date',
        'updated_by',
    ];

    protected $casts = [
        'is_reporting_period' => 'boolean',
        'is_portfolio_group_id' => 'boolean',
        'minimum_value' => 'decimal:2',
        'maximum_value' => 'decimal:2',
        // 'create_date' => 'datetime',
        'update_date' => 'datetime',
        'active_status' => 'integer',
    ];

    // Define constants for active status
    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 0;
    const STATUS_DELETED = 2;

    // Relationships
    public function template(): BelongsTo
    {
        return $this->belongsTo(GeneralImportTemplate::class, 'template_id')
            ->withTrashed();
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active_status', self::STATUS_ACTIVE);
    }

    // Validation methods
    public function validateValue($value)
    {
        if ($value === null || $value === '') {
            return false;
        }

        // Type validation
        if (!$this->validateDataType($value)) {
            return false;
        }

        // Range validation for numeric types
        if (in_array($this->column_data_type, ['integer', 'decimal']) && 
            !$this->validateRange($value)) {
            return false;
        }

        return true;
    }

    protected function validateDataType($value): bool
    {
        switch ($this->column_data_type) {
            case 'integer':
                return is_numeric($value) && (int)$value == $value;
            case 'decimal':
                return is_numeric($value);
            case 'date':
                return strtotime($value) !== false;
            case 'datetime':
                return strtotime($value) !== false;
            case 'boolean':
                return in_array($value, [true, false, 0, 1, '0', '1'], true);
            default:
                return is_string($value);
        }
    }

    protected function validateRange($value): bool
    {
        if ($this->minimum_value !== null && $value < $this->minimum_value) {
            return false;
        }
        if ($this->maximum_value !== null && $value > $this->maximum_value) {
            return false;
        }
        return true;
    }

    public function getValidationRules(): array
    {
        $rules = ['required'];

        switch ($this->column_data_type) {
            case 'integer':
                $rules[] = 'integer';
                break;
            case 'decimal':
                $rules[] = 'numeric';
                break;
            case 'date':
                $rules[] = 'date';
                break;
            case 'datetime':
                $rules[] = 'datetime';
                break;
            case 'boolean':
                $rules[] = 'boolean';
                break;
            default:
                $rules[] = 'string';
        }

        if ($this->minimum_value !== null) {
            $rules[] = "min:{$this->minimum_value}";
        }
        if ($this->maximum_value !== null) {
            $rules[] = "max:{$this->maximum_value}";
        }

        return $rules;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // $model->create_date = now();
            $model->active_status = self::STATUS_ACTIVE;
        });

        static::updating(function ($model) {
            $model->update_date = now();
        });
    }
}