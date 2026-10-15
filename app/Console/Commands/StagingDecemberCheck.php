<?php

namespace App\Console\Commands;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Eir\StagingPolicy;
use App\Services\Eir\StagingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The December 2025 staging check (Staging Module Specification v1,
 * acceptance 4): the system's December 2025 book staged under the 31 December
 * 2025 policy (single-ladder staging), facility by facility against the 108
 * facilities of MAIIC's ECL workbook of 14 December 2025, every difference
 * listed with its cause.
 *
 * The workbook's values are committed as a CSV (docs/bootstrap/models), with
 * the stage the workbook gave each facility from its revised age bracket. The
 * workbook masks customer ids, so a facility is matched to the system's loans
 * by customer name (case, punctuation and company suffixes ignored), and where
 * a customer has more than one facility, by the nearest carrying amount.
 *
 * Causes, in order: the facility is in one book only; the arrears bucket
 * differs (the workbook's age bracket against the bracket of the system's
 * staging day count); the balance differs (more than half a percent and K1,000);
 * or the stage differs for another reason. The system's stage is evaluated
 * under the named policy whatever policy is booked; nothing is written but the
 * CSV.
 *
 *   php artisan staging:december-check
 */
class StagingDecemberCheck extends Command
{
    protected $signature = 'staging:december-check {--period=2025-12} {--workbook=docs/bootstrap/models/ECL model 14 Dec 2025 - portfolio report (values).csv} {--out=}';

    protected $description = 'Stage December 2025 under the 31 December 2025 policy and list every difference from the 108 facilities of the December 2025 ECL workbook, with its cause';

    private const SUFFIXES = ['limited', 'ltd', 'company', 'co', 'plc', 'the', 'and', 'group'];

    public function handle(StagingService $staging): int
    {
        $period = (string) $this->option('period');
        try {
            $given = (string) $this->option('workbook');
            $workbook = $this->workbook(is_file($given) ? $given : base_path($given));
            $evaluated = $staging->evaluate($period, StagingPolicy::named(StagingPolicy::PRESET_SINGLE));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $book = DB::table('loan_books')->where('reporting_period', $period)->whereIn('product_code', array_keys(LandingZoneReader::LOAN_GLS))
            ->get(['contract_id', 'customer_name', 'carrying_amount'])->filter(fn ($r) => abs((float) $r->carrying_amount) > 0.005 || isset($evaluated['loans'][$r->contract_id]))
            ->keyBy('contract_id');

        // match each workbook facility to an unmatched system loan of the same customer, nearest balance first
        $byName = [];
        foreach ($book as $id => $r) {
            $byName[$this->name((string) $r->customer_name)][] = $id;
        }
        $used = [];
        $rows = [];
        // first, a balance equal to the ngwee and unique on both sides: the same facility, whatever the spelling of the
        // name (the workbook's own loan book renames customers: "Infracon Limited" is E-Banker's "Ifracon Limited")
        $cents = fn (float $v) => (string) (int) round($v * 100);
        $wbByBalance = collect($workbook)->groupBy(fn ($w) => $cents($w['carrying']));
        $bookByBalance = $book->groupBy(fn ($r) => $cents((float) $r->carrying_amount));
        $byBalance = [];
        foreach ($workbook as $i => $w) {
            $k = $cents($w['carrying']);
            if (abs($w['carrying']) > 0.005 && $wbByBalance[$k]->count() === 1 && isset($bookByBalance[$k]) && $bookByBalance[$k]->count() === 1) {
                $byBalance[$i] = $bookByBalance[$k]->first()->contract_id;
            }
        }
        foreach ($workbook as $i => $w) {
            if (isset($byBalance[$i])) {
                $id = $byBalance[$i];
                $used[$id] = true;
                $row = $this->compare($w, $book[$id], $evaluated['loans'][$id] ?? null);
                $row['matched_on'] = $this->name($w['customer_name']) === $this->name((string) $book[$id]->customer_name) ? 'name and balance' : 'balance (names differ)';
                $rows[] = $row;
                continue;
            }
            $candidates = array_values(array_filter($byName[$this->name($w['customer_name'])] ?? [], fn ($id) => ! isset($used[$id])));
            usort($candidates, fn ($a, $b) => abs((float) $book[$a]->carrying_amount - $w['carrying']) <=> abs((float) $book[$b]->carrying_amount - $w['carrying']));
            $id = $candidates[0] ?? null;
            if ($id === null) {
                $rows[] = $this->row($w, null, null, 'in the workbook only: no loan of this customer in the system\'s book');
                continue;
            }
            $used[$id] = true;
            $row = $this->compare($w, $book[$id], $evaluated['loans'][$id] ?? null);
            $row['matched_on'] = 'name';
            $rows[] = $row;
        }
        foreach ($book as $id => $r) {
            if (! isset($used[$id]) && abs((float) $r->carrying_amount) > 0.005) {
                $rows[] = $this->row(null, $r, $evaluated['loans'][$id] ?? null, 'in the system\'s book only: no facility of this customer in the workbook');
            }
        }

        $summary = collect($rows)->countBy('cause');
        $out = (string) ($this->option('out') ?: storage_path("app/staging-checks/December 2025 workbook check {$period}.csv"));
        @mkdir(dirname($out), 0775, true);
        $f = fopen($out, 'w');
        fputcsv($f, ['Workbook customer', 'Workbook balance', 'Workbook bracket', 'Workbook stage', 'Contract', 'System customer', 'System balance', 'System days (staging)', 'System bracket', 'System stage (31 Dec 2025 policy)', 'Same stage', 'Cause', 'Matched on']);
        foreach ($rows as $r) {
            fputcsv($f, [$r['w_name'], $r['w_balance'], $r['w_bracket'], $r['w_stage'], $r['contract'], $r['s_name'], $r['s_balance'], $r['s_days'], $r['s_bracket'], $r['s_stage'], $r['same'] ? 'yes' : 'no', $r['cause'], $r['matched_on'] ?? '']);
        }
        fclose($f);

        $matched = collect($rows)->filter(fn ($r) => $r['contract'] !== null && $r['w_name'] !== null);
        $this->info(sprintf('%s: %d workbook facilities, %d matched to the system\'s book, %d with the same stage, %d differing.', $period, count($workbook), $matched->count(), $matched->where('same', true)->count(), $matched->where('same', false)->count()));
        $this->table(['Cause', 'Facilities'], $summary->map(fn ($n, $c) => [$c, $n])->values()->all());
        $this->line("Every facility, with its cause: {$out}");

        return self::SUCCESS;
    }

    /** @return list<array{customer_name:string,carrying:float,bracket:string,stage:int}> */
    private function workbook(string $path): array
    {
        if (! is_file($path)) {
            throw new \RuntimeException("The workbook values file is not at {$path}.");
        }
        $f = fopen($path, 'r');
        fgetcsv($f); // the provenance line
        $head = fgetcsv($f);
        $out = [];
        while (($r = fgetcsv($f)) !== false) {
            $d = array_combine($head, $r);
            $out[] = ['customer_name' => (string) $d['customer_name'], 'carrying' => (float) $d['carrying_amount'], 'bracket' => (string) $d['age_bracket_revised'], 'stage' => (int) $d['workbook_stage']];
        }
        fclose($f);

        return $out;
    }

    private function compare(array $w, object $loan, ?array $staged): array
    {
        $bracket = $staged === null ? null : $this->bracket((int) $staged['dpd']);
        $wBracket = $this->bracket($this->bracketDays($w['bracket']));
        $balanceDiffers = abs((float) $loan->carrying_amount - $w['carrying']) > max(1000, 0.005 * abs($w['carrying']));
        $sameStage = $staged !== null && (int) $staged['stage'] === $w['stage'];
        $cause = match (true) {
            $staged === null => 'in the system\'s book but not staged',
            $bracket !== $wBracket => 'different arrears bucket',
            $balanceDiffers => 'different balance',
            ! $sameStage => 'same bucket and balance, different stage',
            default => 'same',
        };

        return $this->row($w, $loan, $staged, $cause);
    }

    private function row(?array $w, ?object $loan, ?array $staged, string $cause): array
    {
        return [
            'w_name' => $w['customer_name'] ?? null, 'w_balance' => $w === null ? null : round($w['carrying'], 2), 'w_bracket' => $w['bracket'] ?? null, 'w_stage' => $w['stage'] ?? null,
            'contract' => $loan?->contract_id, 's_name' => $loan?->customer_name, 's_balance' => $loan === null ? null : round((float) $loan->carrying_amount, 2),
            's_days' => $staged['dpd'] ?? null, 's_bracket' => $staged === null ? null : $this->bracket((int) $staged['dpd']), 's_stage' => $staged['stage'] ?? null,
            'same' => $w !== null && $staged !== null && (int) $staged['stage'] === (int) $w['stage'], 'cause' => $cause,
        ];
    }

    /** The workbook's brackets: 1-30 (current included), 31-90, 91-180, over 180. */
    private function bracket(int $days): string
    {
        return match (true) {
            $days <= 30 => '1-30 Days',
            $days <= 90 => '31-90 Days',
            $days <= 180 => '91-180 Days',
            default => '>181 Days',
        };
    }

    private function bracketDays(string $bracket): int
    {
        return match (strtolower(trim($bracket))) {
            '31-90 days' => 31,
            '91-180 days' => 91,
            '>181 days' => 181,
            default => 0,
        };
    }

    private function name(string $name): string
    {
        $words = preg_split('/\s+/', trim(preg_replace('/[^a-z0-9 ]+/', ' ', strtolower($name))));

        return implode(' ', array_filter($words, fn ($w) => $w !== '' && ! in_array($w, self::SUFFIXES, true)));
    }
}
