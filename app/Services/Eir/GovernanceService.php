<?php

namespace App\Services\Eir;

use App\Exceptions\GovernanceSettingMissingException;
use App\Models\GovernanceSetting;
use App\Models\GovernanceSettingHistory;
use App\Services\AuditLoggerService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

/**
 * The Governance Centre (spec v3 section 8, decision D17).
 *
 * Every convention the EIR engine uses is a setting with a fixed list of
 * options, an effective date, a proposer and an approver. The value in force
 * on a date is the latest APPROVED row whose effective_from is on or before
 * that date. A change applies forward only: it must start later than the
 * change before it, so a locked period keeps the settings it was locked
 * under. No value is ever defaulted in code; a key with no approved value
 * raises GovernanceSettingMissingException and the calculation stops.
 */
class GovernanceService
{
    /** Resolved values, keyed "key|date", so a loop over contracts asks once. */
    private array $memo = [];

    /**
     * The twelve conventions of spec v3 section 8, in the order the screen
     * shows them. The default is Dupleix's recommendation; the seeder writes
     * it as the first APPROVED row and the engine reads only the database.
     *
     * @return array<string, array{label:string, description:string, options:list<string>, default:string}>
     */
    public static function catalogue(): array
    {
        return [
            'plr_mid_period' => [
                'label' => 'PLR change inside a month',
                'description' => 'When the Reserve Bank prime lending rate changes part-way through a month, this decides from which day the new rate applies to a floating loan. Open choice O2 in the specification; not yet agreed with MAIIC.',
                'options' => ['Pro rata from the effective date', 'From the next month-end', 'From the next instalment date'],
                'default' => 'Pro rata from the effective date',
            ],
            'reset_trigger' => [
                'label' => 'When a floating loan\'s EIR is re-solved',
                'description' => 'A floating loan gets a fresh effective interest rate whenever its reference rate changes (IFRS 9 B5.4.5). This decides the date on which the engine re-solves it. Agreed as decision D8.',
                'options' => ['At the PLR effective date', 'At the next instalment date', 'At month-end'],
                'default' => 'At the PLR effective date',
            ],
            'moratorium_capitalisation' => [
                'label' => 'How a "Both" moratorium compounds',
                'description' => 'During a moratorium of type Both (interest and principal deferred) the interest is added to the balance. This decides how often. E-Banker\'s own postings show monthly compounding on every moratorium loan in the sample (decision D10).',
                'options' => ['Monthly at posting', 'At the instalment frequency'],
                'default' => 'Monthly at posting',
            ],
            'rate_source_precedence' => [
                'label' => 'Which source decides whether a loan reprices',
                'description' => 'Three sources can say whether a loan follows the prime rate: the Interest Policy code held in E-Banker, the rate movements observed in the monthly loan books, and the product family. This sets the order in which the engine trusts them. Open choice O3.',
                'options' => ['Interest Policy, then observed, then product family', 'Observed behaviour first', 'Product family only'],
                'default' => 'Interest Policy, then observed, then product family',
            ],
            'margin_basis' => [
                'label' => 'Where the spread over prime comes from',
                'description' => 'The spread added to the prime rate (margin) can be derived from the rate live at drawdown (LIVE_AT_DRAWDOWN), taken as captured in E-Banker (AS_CAPTURED), or supplied by MAIIC in the contract master (SUPPLIED). Open choice O1; it depends on the answer about the reference-rate refresh button.',
                'options' => ['LIVE_AT_DRAWDOWN', 'AS_CAPTURED', 'SUPPLIED'],
                'default' => 'LIVE_AT_DRAWDOWN',
            ],
            'stage3_interest_basis' => [
                'label' => 'Interest on Stage 3 loans',
                'description' => 'For a credit-impaired (Stage 3) loan, IFRS 9 5.4.1(b) applies the EIR to the amortised cost net of the loss allowance. The alternative accrues on the gross amount and unwinds the allowance separately. Agreed as decision D11.',
                'options' => ['Net carrying amount', 'Gross with allowance unwind'],
                'default' => 'Net carrying amount',
            ],
            'day_count' => [
                'label' => 'Day count',
                'description' => 'How a period\'s interest is measured: actual days over 365 (ACT/365, E-Banker\'s own convention, reproduced to the cent) or thirty-day months over 360 (30/360). Agreed as decision D9.',
                'options' => ['ACT/365', '30/360'],
                'default' => 'ACT/365',
            ],
            'cash_source' => [
                'label' => 'Where actual cash received comes from',
                'description' => 'The cash a customer paid in a month can be read from the monthly Loan Book Report (the increase in the cumulative Repayments column), from the transaction ledger (Extract B), or assumed from the contractual schedule. Agreed as decision D14.',
                'options' => ['Loan book (change in Repayments)', 'Extract B (transaction ledger)', 'Contractual schedule'],
                'default' => 'Loan book (change in Repayments)',
            ],
            'manual_policy_handling' => [
                'label' => 'Loans with Interest Policy = Manual',
                'description' => 'Some accounts carry an Interest Policy of Manual, so E-Banker does not reprice them on its own. The engine can read their rate every month and treat each change as a reset, or treat them as fixed for life. Open choice O4.',
                'options' => ['Read the rate monthly, each change is a reset', 'Treat as fixed'],
                'default' => 'Read the rate monthly, each change is a reset',
            ],
            'modification_threshold' => [
                'label' => 'The derecognition test',
                'description' => 'When a loan is restructured, the present value of the new cash flows at the original EIR is compared with the old carrying amount. A change beyond this threshold means the old loan is derecognised and a new one recognised; below it, the difference is a modification gain or loss. Open choice O16.',
                'options' => ['10 percent', '5 percent', '15 percent'],
                'default' => '10 percent',
            ],
            'recon_tolerance' => [
                'label' => 'When a difference is an exception',
                'description' => 'The reconciliation compares the engine\'s interest with what the ledger posted, account by account and month by month. A difference inside this band is treated as agreeing; outside it, the row is an exception that has to be explained by a named cause. The band is a share of the amount posted with a floor in kwacha, so that near-zero postings do not raise false exceptions. Open choice O11.',
                'options' => [
                    '1 percent of the posted amount, floor MWK 1',
                    '0.5 percent of the posted amount, floor MWK 1',
                    '100 basis points on the EIR and MWK 1 per account-month',
                    'MWK 100 per account-month, whatever the amount posted',
                ],
                'default' => '1 percent of the posted amount, floor MWK 1',
            ],
            'counter_reset_handling' => [
                'label' => 'A Repayments counter that falls',
                'description' => 'The loan book\'s Repayments column is cumulative and should only rise. When it falls (a restructure, a settlement or a data reset), this decides what the engine does with that month\'s cash: treat it as a discontinuity and take cash from Extract B, treat it as a settlement, or hold the account for manual review. Open choice O5.',
                'options' => ['Discontinuity: take cash from Extract B', 'Settlement', 'Manual review'],
                'default' => 'Discontinuity: take cash from Extract B',
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::catalogue());
    }

    /**
     * The value in force for a key on a date (today by default).
     *
     * @throws GovernanceSettingMissingException when no approved value applies
     */
    public function get(string $key, ?CarbonInterface $asOf = null): string
    {
        $date = ($asOf ?? CarbonImmutable::today())->toDateString();
        $memoKey = $key . '|' . $date;
        if (! array_key_exists($memoKey, $this->memo)) {
            $row = $this->inForce($key, $asOf);
            if ($row === null) {
                throw GovernanceSettingMissingException::forKey($key, $date);
            }
            $this->memo[$memoKey] = $row->value;
        }

        return $this->memo[$memoKey];
    }

    /** The approved row in force for a key on a date, or null when there is none. */
    public function inForce(string $key, ?CarbonInterface $asOf = null): ?GovernanceSetting
    {
        $date = ($asOf ?? CarbonImmutable::today())->toDateString();

        return GovernanceSetting::query()
            ->where('key', $key)
            ->where('status', GovernanceSetting::STATUS_APPROVED)
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The options a key may take, from the catalogue.
     *
     * @return list<string>
     * @throws InvalidArgumentException for a key the catalogue does not know
     */
    public function options(string $key): array
    {
        $catalogue = self::catalogue();
        if (! isset($catalogue[$key])) {
            throw new InvalidArgumentException("'{$key}' is not a governance setting the engine knows.");
        }

        return $catalogue[$key]['options'];
    }

    /**
     * Record a proposed change. The proposal takes no effect until a second
     * person approves it (see approve()). A value outside the option list, an
     * effective date that is not later than the last approved change, or a
     * duplicate (key, date) is refused with a named reason.
     */
    public function propose(string $key, string $value, CarbonInterface|string $effectiveFrom, string $reason, ?int $userId): GovernanceSetting
    {
        $options = $this->options($key);
        $definition = self::catalogue()[$key];
        $value = trim($value);
        if (! in_array($value, $options, true)) {
            throw new InvalidArgumentException("'{$value}' is not one of the options for {$definition['label']}: " . implode(' / ', $options) . '.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('A change needs a specific reason of at least 10 characters.');
        }

        $effective = $this->date($effectiveFrom);

        return DB::transaction(function () use ($key, $value, $effective, $reason, $userId, $definition) {
            $this->assertEffectiveDateIsLater($key, $effective, null);

            $inForce = $this->inForce($key, $effective);
            if ($inForce !== null && $inForce->value === $value) {
                throw new LogicException("{$definition['label']} is already '{$value}' from {$inForce->effective_from->toDateString()}; there is nothing to change.");
            }

            $setting = GovernanceSetting::create([
                'key' => $key,
                'value' => $value,
                'options' => $definition['options'],
                'label' => $definition['label'],
                'description' => $definition['description'],
                'effective_from' => $effective->toDateString(),
                'set_by' => $userId,
                'reason' => mb_substr($reason, 0, 500),
                'status' => GovernanceSetting::STATUS_PROPOSED,
            ]);

            AuditLoggerService::log('EIR Governance Setting Proposed', GovernanceSetting::class, $setting->id, [
                'old_values' => $inForce ? ['value' => $inForce->value, 'effective_from' => $inForce->effective_from->toDateString()] : null,
                'new_values' => ['key' => $key, 'value' => $value, 'effective_from' => $effective->toDateString(), 'reason' => $reason],
                'meta' => ['proposed_by' => $userId, 'first_period_applied' => $effective->format('Y-m')],
            ]);

            return $setting;
        });
    }

    /**
     * Approve a proposal. The approver must be a different person from the
     * proposer unless an administrator overrides, the same rule the EIR lock
     * applies. The row previously in force is copied to the history table at
     * this moment, because this is when its replacement was decided; it stays
     * in the live table so that dates before the change still resolve to it.
     */
    public function approve(int $settingId, int $approverId, bool $allowMakerCheckerOverride = false): GovernanceSetting
    {
        return DB::transaction(function () use ($settingId, $approverId, $allowMakerCheckerOverride) {
            $setting = GovernanceSetting::query()->lockForUpdate()->findOrFail($settingId);
            if ($setting->status !== GovernanceSetting::STATUS_PROPOSED) {
                throw new LogicException('Only a proposed change can be approved.');
            }
            if (! $allowMakerCheckerOverride && $setting->set_by === null) {
                throw new LogicException('The proposal has no identifiable proposer and cannot be approved.');
            }
            if (! $allowMakerCheckerOverride && (int) $setting->set_by === $approverId) {
                throw new LogicException('The person who proposed a change cannot approve it.');
            }

            $effective = CarbonImmutable::parse($setting->effective_from->toDateString());
            $this->assertEffectiveDateIsLater($setting->key, $effective, $setting->id);

            $superseded = $this->inForce($setting->key, $effective);
            if ($superseded !== null) {
                GovernanceSettingHistory::create([
                    'setting_id' => $superseded->id,
                    'key' => $superseded->key,
                    'value' => $superseded->value,
                    'options' => $superseded->options,
                    'label' => $superseded->label,
                    'description' => $superseded->description,
                    'effective_from' => $superseded->effective_from->toDateString(),
                    'set_by' => $superseded->set_by,
                    'approved_by' => $superseded->approved_by,
                    'approved_at' => $superseded->approved_at,
                    'reason' => $superseded->reason,
                    'status' => $superseded->status,
                    'superseded_at' => now(),
                    'superseded_by' => $approverId,
                    'superseded_by_setting_id' => $setting->id,
                ]);
            }

            $setting->update([
                'status' => GovernanceSetting::STATUS_APPROVED,
                'approved_by' => $approverId,
                'approved_at' => now(),
            ]);
            $this->memo = [];

            AuditLoggerService::log('EIR Governance Setting Approved', GovernanceSetting::class, $setting->id, [
                'old_values' => $superseded ? ['value' => $superseded->value, 'effective_from' => $superseded->effective_from->toDateString()] : null,
                'new_values' => ['key' => $setting->key, 'value' => $setting->value, 'effective_from' => $effective->toDateString(), 'reason' => $setting->reason],
                'meta' => [
                    'proposed_by' => $setting->set_by,
                    'approved_by' => $approverId,
                    'admin_override' => $allowMakerCheckerOverride,
                    'first_period_applied' => $effective->format('Y-m'),
                ],
            ]);

            return $setting->fresh();
        });
    }

    /**
     * Everything the Governance Centre screen shows: each catalogue setting
     * with its value in force on the date, every row (past, in force, upcoming,
     * proposed) and the history of superseded rows.
     *
     * @return list<array>
     */
    public function overview(?CarbonInterface $asOf = null): array
    {
        $asOf = $asOf ?? CarbonImmutable::today();
        $rows = GovernanceSetting::with(['proposer:id,name', 'approver:id,name'])
            ->orderByDesc('effective_from')->orderByDesc('id')->get()->groupBy('key');
        $history = GovernanceSettingHistory::with(['proposer:id,name', 'approver:id,name', 'superseder:id,name'])
            ->orderByDesc('superseded_at')->orderByDesc('id')->get()->groupBy('key');

        $overview = [];
        foreach (self::catalogue() as $key => $definition) {
            $inForce = $this->inForce($key, $asOf);
            $overview[] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'options' => $definition['options'],
                'default' => $definition['default'],
                'in_force' => $inForce ? $this->presentRow($inForce, $inForce, $asOf) : null,
                'rows' => $rows->get($key, collect())->map(fn ($row) => $this->presentRow($row, $inForce, $asOf))->values()->all(),
                'history' => $history->get($key, collect())->map(fn ($row) => [
                    'id' => $row->id,
                    'setting_id' => $row->setting_id,
                    'value' => $row->value,
                    'effective_from' => $row->effective_from->toDateString(),
                    'reason' => $row->reason,
                    'proposer' => $row->proposer?->name,
                    'approver' => $row->approver?->name,
                    'approved_at' => $row->approved_at?->toDateTimeString(),
                    'superseded_at' => $row->superseded_at?->toDateTimeString(),
                    'superseded_by' => $row->superseder?->name,
                    'superseded_by_setting_id' => $row->superseded_by_setting_id,
                ])->values()->all(),
            ];
        }

        return $overview;
    }

    /**
     * The reconciliation band in force on a date, as numbers.
     *
     * @return array{percent:float, floor:float}
     */
    public function reconciliationTolerance(?CarbonInterface $asOf = null): array
    {
        return self::parseTolerance($this->get('recon_tolerance', $asOf));
    }

    /** The Stage 3 accrual basis in force on a date: NET or GROSS. */
    public function stage3InterestBasis(?CarbonInterface $asOf = null): string
    {
        return self::parseStage3Basis($this->get('stage3_interest_basis', $asOf));
    }

    /**
     * Read a recon_tolerance option into a share of the posted amount and a
     * kwacha floor. "N percent" and "N basis points" both give the share; an
     * option with no share at all is an absolute threshold (share zero).
     *
     * @return array{percent:float, floor:float}
     */
    public static function parseTolerance(string $option): array
    {
        $percent = null;
        if (preg_match('/(\d+(?:\.\d+)?)\s*percent/i', $option, $m) === 1) {
            $percent = (float) $m[1];
        } elseif (preg_match('/(\d+(?:\.\d+)?)\s*basis\s*points?/i', $option, $m) === 1) {
            $percent = ((float) $m[1]) / 100;
        }

        $floor = null;
        if (preg_match('/MWK\s*([\d,]+(?:\.\d+)?)/i', $option, $m) === 1) {
            $floor = (float) str_replace(',', '', $m[1]);
        }

        if ($floor === null && $percent === null) {
            throw new InvalidArgumentException("The tolerance option '{$option}' names neither a share of the posted amount nor a kwacha floor.");
        }

        return ['percent' => $percent ?? 0.0, 'floor' => $floor ?? 0.0];
    }

    /** Read a stage3_interest_basis option into the accrual basis the roll-forward stores. */
    public static function parseStage3Basis(string $option): string
    {
        $text = strtolower(trim($option));
        if (str_starts_with($text, 'net')) {
            return 'NET';
        }
        if (str_starts_with($text, 'gross')) {
            return 'GROSS';
        }

        throw new InvalidArgumentException("The Stage 3 basis option '{$option}' is neither net nor gross.");
    }

    /**
     * A change applies forward only: its effective date must be later than
     * every approved change before it, so no governed period is restated.
     * The same (key, date) may exist once, whatever its status.
     */
    private function assertEffectiveDateIsLater(string $key, CarbonImmutable $effective, ?int $ignoreId): void
    {
        $latest = GovernanceSetting::query()->where('key', $key)
            ->where('status', GovernanceSetting::STATUS_APPROVED)
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '<>', $ignoreId))
            ->max('effective_from');
        $latestDate = $latest === null ? null : CarbonImmutable::parse($latest)->toDateString();
        if ($latestDate !== null && $effective->toDateString() <= $latestDate) {
            throw new LogicException("The effective date must be later than {$latestDate}, the date of the last approved change for '{$key}'. A change never restates a period already governed.");
        }

        $duplicate = GovernanceSetting::query()->where('key', $key)
            ->whereDate('effective_from', $effective->toDateString())
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '<>', $ignoreId))
            ->exists();
        if ($duplicate) {
            throw new LogicException("A change to '{$key}' effective {$effective->toDateString()} already exists; choose another date.");
        }
    }

    private function date(CarbonInterface|string $value): CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::parse($value->toDateString());
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) !== 1) {
            throw new InvalidArgumentException('The effective date must be written as yyyy-mm-dd.');
        }

        return CarbonImmutable::createFromFormat('Y-m-d', trim($value))->startOfDay();
    }

    private function presentRow(GovernanceSetting $row, ?GovernanceSetting $inForce, CarbonInterface $asOf): array
    {
        $effective = $row->effective_from->toDateString();
        $state = match (true) {
            $row->status === GovernanceSetting::STATUS_PROPOSED => 'PROPOSED',
            $inForce !== null && $row->id === $inForce->id => 'IN_FORCE',
            $effective > $asOf->toDateString() => 'UPCOMING',
            default => 'PAST',
        };

        return [
            'id' => $row->id,
            'key' => $row->key,
            'value' => $row->value,
            'effective_from' => $effective,
            'status' => $row->status,
            'state' => $state,
            'reason' => $row->reason,
            'proposer' => $row->proposer?->name,
            'proposer_id' => $row->set_by,
            'approver' => $row->approver?->name,
            'approved_at' => $row->approved_at?->toDateTimeString(),
            'created_at' => $row->created_at?->toDateTimeString(),
        ];
    }
}
