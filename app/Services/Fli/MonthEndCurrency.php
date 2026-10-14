<?php

namespace App\Services\Fli;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a month's ECL and its month record are current (14 October 2026).
 *
 * The Maintenance month-end restages the book and re-runs the PD and the LGD
 * before the forward-looking route, the ECL and the record. When it stops
 * part-way (at the route, say), the restaging and the PD run have already
 * happened, and the PD run has cleared the post-FLI PD, but the ECL rows and
 * the month record of the earlier run are still on the loans and in
 * fli_run_snapshots. They are not wrong as history, but they no longer
 * describe the month: something they were calculated from ran again after
 * them. Nothing is deleted or rewritten here; the screens ask this class and
 * say the month is mid-run, and the record cannot be signed off until the
 * remaining steps have run.
 *
 * An upstream step is read from the audit trail each step writes (staging,
 * the PD run, the LGD run, the route) and from the ECL time on the loans; a
 * step that ran later than what was calculated from it makes that stale.
 * Equal times (the same second) are not stale.
 */
class MonthEndCurrency
{
    /** The steps whose re-run makes the ECL and the record stale, as the audit trail names them. */
    public const UPSTREAM = [
        'Loan Book Staged' => 'the staging',
        'PD Segment Run' => 'the PD run',
        'LGD Segment Run' => 'the LGD run',
        'FLI Route Applied' => 'the forward-looking route',
    ];

    /**
     * @return array{mid_run:bool,ecl_stale:?string,record_stale:?string,message:?string,ecl_at:?string,record_at:?string,record_id:?int}
     */
    public static function state(string $period): array
    {
        $out = ['mid_run' => false, 'ecl_stale' => null, 'record_stale' => null, 'message' => null, 'ecl_at' => null, 'record_at' => null, 'record_id' => null];
        if (! preg_match('/^\d{4}-\d{2}$/', $period) || ! Schema::hasTable('loan_books') || ! Schema::hasTable('audit_logs')) {
            return $out;
        }
        $ran = DB::table('audit_logs')->where('reporting_period', $period)->whereIn('action', array_keys(self::UPSTREAM))
            ->groupBy('action')->selectRaw('action, max(created_at) at')->pluck('at', 'action')->all();
        $eclAt = DB::table('loan_books')->where('reporting_period', $period)->max('ecl_calculated_at');
        $record = Schema::hasTable('fli_run_snapshots')
            ? DB::table('fli_run_snapshots')->where('reporting_period', $period)->whereIn('status', ['RECORDED', 'SIGNED_OFF'])->orderByDesc('id')->first(['id', 'created_at'])
            : null;
        $out['ecl_at'] = $eclAt === null ? null : (string) $eclAt;
        $out['record_at'] = $record?->created_at === null ? null : (string) $record->created_at;
        $out['record_id'] = $record ? (int) $record->id : null;

        $after = function (?string $at) use ($ran): array {
            if ($at === null) {
                return [];
            }
            $later = [];
            foreach (self::UPSTREAM as $action => $label) {
                if (isset($ran[$action]) && self::later((string) $ran[$action], $at)) {
                    $later[] = $label . ' (' . $ran[$action] . ')';
                }
            }

            return $later;
        };
        $eclLater = $after($out['ecl_at']);
        if ($eclLater !== []) {
            $out['ecl_stale'] = 'The ECL of ' . $period . ' was calculated at ' . $out['ecl_at'] . ', before ' . implode(', ', $eclLater) . ' ran again: it is not current.';
        }
        $recordLater = $after($out['record_at']);
        if ($out['record_at'] !== null && $out['ecl_at'] !== null && self::later($out['ecl_at'], $out['record_at'])) {
            $recordLater[] = 'the ECL (' . $out['ecl_at'] . ')';
        }
        if ($recordLater !== []) {
            $out['record_stale'] = 'The month record of ' . $period . ' (' . $out['record_at'] . ') was made before ' . implode(', ', $recordLater) . ' ran again: it is not current.';
        }
        if ($out['ecl_stale'] !== null || $out['record_stale'] !== null) {
            $out['mid_run'] = true;
            $out['message'] = 'The month-end of ' . $period . ' is mid-run: ' . implode(' ', array_filter([$out['ecl_stale'], $out['record_stale']]))
                . ' Run the remaining steps (Maintenance, the month-end; or Forward-Looking Information, Overview) to bring the ECL and the record up to date.';
        }

        return $out;
    }

    private static function later(string $a, string $b): bool
    {
        return CarbonImmutable::parse($a)->gt(CarbonImmutable::parse($b));
    }
}
