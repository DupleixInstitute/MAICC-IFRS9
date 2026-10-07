<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One upload event in the Layer-1 raw-source manifest.
 *
 * @property int $id
 * @property string $batch_ref                 unique, e.g. UUID v4
 * @property string $source_kind               'historical_csv' | 'financial_data_csv' | 'bsa1_workbook' | ...
 * @property string|null $primary_filename
 * @property string $status                    pending | parsing | completed | failed | partial
 * @property string|null $statement_type
 * @property string|null $reporting_period
 * @property int|null $year
 * @property int|null $month
 * @property int|null $quarter
 * @property string|null $currency
 * @property int $file_count
 * @property int $row_count
 * @property int $accepted_row_count
 * @property int $rejected_row_count
 * @property string|null $error_summary
 * @property int|null $linked_dataset_id
 * @property int|null $linked_import_log_id
 * @property int|null $imported_by_user_id
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 */
class SourceImportBatch extends Model
{
    protected $table = 'source_import_batches';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PARSING   = 'parsing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_PARTIAL   = 'partial';

    public const SOURCE_HISTORICAL_CSV    = 'historical_csv';
    public const SOURCE_FINANCIAL_CSV     = 'financial_data_csv';
    public const SOURCE_SOCE_CSV          = 'soce_csv';
    public const SOURCE_BSA1_WORKBOOK     = 'bsa1_workbook';
    public const SOURCE_LOAN_TAPE         = 'loan_tape';
    public const SOURCE_DEPOSIT_TAPE      = 'deposit_tape';
    public const SOURCE_MACRO_CSV         = 'macro_csv';
    public const SOURCE_OP_LOSS_CSV       = 'op_loss_csv';
    public const SOURCE_MANUAL_ENTRY      = 'manual_entry';
    // Universal import-log coverage: one source_kind per importer so every
    // import in the system is recorded against a single master batch table.
    public const SOURCE_MACRO_WORLDBANK   = 'macro_worldbank';
    public const SOURCE_MACRO_IMF_WEO     = 'macro_imf_weo';
    public const SOURCE_BORROWINGS_TAPE   = 'borrowings_tape';
    public const SOURCE_TREASURY_PLACEMENTS = 'treasury_placements_tape';
    public const SOURCE_SECURITIES_TAPE   = 'securities_tape';
    public const SOURCE_OFF_BALANCE_TAPE  = 'off_balance_tape';
    public const SOURCE_IFRS9_CALIBRATION = 'ifrs9_calibration';
    public const SOURCE_RWA_SCHEDULE14    = 'rwa_schedule14';
    public const SOURCE_LARGE_DEPOSITOR   = 'large_depositor_tape';
    public const SOURCE_LARGE_EXPOSURE    = 'large_exposure_tape';
    public const SOURCE_OPRISK_GOI        = 'oprisk_goi';
    public const SOURCE_RAS_OBSERVATIONS  = 'ras_observations';
    public const SOURCE_COA_MAPPING       = 'coa_mapping';
    public const SOURCE_REGULATORY_RETURN = 'regulatory_return';
    public const SOURCE_CSP_FORECAST      = 'znbs_csp_forecast';

    protected $fillable = [
        'batch_ref',
        'source_kind',
        'primary_filename',
        'status',
        'statement_type',
        'reporting_period',
        'year', 'month', 'quarter',
        'currency',
        'file_count', 'row_count', 'accepted_row_count', 'rejected_row_count',
        'error_summary',
        'linked_dataset_id',
        'linked_import_log_id',
        'cycle_id',
        'imported_by_user_id',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'year'               => 'integer',
        'month'              => 'integer',
        'quarter'            => 'integer',
        'file_count'         => 'integer',
        'row_count'          => 'integer',
        'accepted_row_count' => 'integer',
        'rejected_row_count' => 'integer',
        'started_at'         => 'datetime',
        'finished_at'        => 'datetime',
    ];

    public function files(): HasMany
    {
        return $this->hasMany(SourceImportFile::class, 'source_import_batch_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(SourceImportRow::class, 'source_import_batch_id');
    }

    public function linkedDataset(): BelongsTo
    {
        return $this->belongsTo(FinancialDataSet::class, 'linked_dataset_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(RegulatoryIntakeCycle::class, 'cycle_id');
    }

    public function linkedImportLog(): BelongsTo
    {
        return $this->belongsTo(FinancialImportLog::class, 'linked_import_log_id');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_user_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_PARTIAL,
        ], true);
    }
}
