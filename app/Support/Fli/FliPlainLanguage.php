<?php

namespace App\Support\Fli;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Presentation only: the forward-looking screens' codes and the finder's
 * technical reasons in the words the finance team uses. The names come from
 * the macro definitions (macro_statistics, then macro_series) and from the
 * proxy names the bridge writes (credit_loss_series.proxy_name), so a code
 * cut short in the engine's tables is never shown cut short. Nothing here is
 * read by an engine.
 */
class FliPlainLanguage
{
    /** The proxy measures the bridge derives, longest prefix first. */
    private const MEASURES = [
        'DEFAULT_RATE_12M' => '12-month default rate',
        'STAGE3_SHARE' => 'Stage 3 share of accounts',
        'NPL_RATIO' => 'NPL ratio',
    ];

    /** @var array<string,string> */
    private array $drivers = [];

    /** @var array<string,string> proxy code => the bridge's proxy name */
    private array $proxyNames = [];

    /** @var array<string,string> structural event code => name */
    private array $events = [];

    public function __construct()
    {
        try {
            if (Schema::hasTable('macro_series')) {
                $this->drivers = DB::table('macro_series')->whereNotNull('statistic_name')->distinct()->pluck('statistic_name', 'statistic_code')->all();
            }
            if (Schema::hasTable('macro_statistics')) {
                $this->drivers = DB::table('macro_statistics')->whereNotNull('statistic_code')->pluck('statistic_name', 'statistic_code')->all() + $this->drivers;
            }
            if (Schema::hasTable('credit_loss_series')) {
                $this->proxyNames = DB::table('credit_loss_series')->whereNotNull('proxy_name')->distinct()->pluck('proxy_name', 'proxy_code')->all();
            }
            if (Schema::hasTable('structural_events')) {
                $this->events = DB::table('structural_events')->pluck('name', 'code')->all();
            }
        } catch (Throwable) {
        }
    }

    /** A driver's full name, e.g. CPI -> "Inflation, consumer prices". */
    public function driver(?string $code): string
    {
        $code = (string) $code;

        return $this->drivers[$code] ?? ucfirst(strtolower(str_replace('_', ' ', $code)));
    }

    /** A driver's name inside a sentence: "Inflation (consumer prices)". */
    private function driverInSentence(string $code): string
    {
        $name = $this->driver($code);

        return str_contains($name, ', ') ? preg_replace('/, /', ' (', $name, 1) . ')' : $name;
    }

    /**
     * A proxy in plain words: the measure, the loans it covers, the full name
     * ("NPL ratio, MAIIC agricultural loans") and the bridge's definition.
     *
     * @return array{measure:string,segment:?string,name:string,definition:string}
     */
    public function proxy(?string $code): array
    {
        $code = (string) $code;
        $definition = (string) ($this->proxyNames[$code] ?? $code);
        $measure = null;
        foreach (self::MEASURES as $prefix => $label) {
            if (str_starts_with($code, $prefix)) {
                $measure = $label;
                break;
            }
        }
        // the loans covered are what the bridge wrote after the definition's first comma
        $segment = null;
        if (preg_match('/^[^,]+,\s*(.+)$/', $definition, $m)) {
            $segment = $this->segment($m[1]);
        }
        if ($measure === null) {
            $measure = preg_replace('/,.*$/', '', $definition) ?: $code;
        }

        return ['measure' => $measure, 'segment' => $segment, 'name' => $measure . ', ' . ($segment ?? 'whole book'), 'definition' => $definition];
    }

    /** "MAIIC Agricultural Loans" -> "MAIIC agricultural loans"; acronyms and mixed-case names stay as written. */
    private function segment(string $s): string
    {
        return implode(' ', array_map(fn ($w) => preg_match('/^[A-Z][a-z]+$/', $w) ? strtolower($w) : $w, explode(' ', trim($s))));
    }

    /** "202407" -> "Jul 2024". */
    private function month(string $ym): string
    {
        try {
            return CarbonImmutable::createFromFormat('Ym', $ym)->startOfMonth()->format('M Y');
        } catch (Throwable) {
            return $ym;
        }
    }

    /**
     * One suggestion of the finder as a sentence, built from the same fields
     * as its technical reason (the reason itself stays available as detail).
     *
     * @param  array{statistic_code:string,proxy_code:string,lag_months:int|string|null,r_squared:float|string|null,reason:?string}  $s
     */
    public function suggestion(array $s): string
    {
        $reason = (string) ($s['reason'] ?? '');
        $driver = $this->driverInSentence((string) $s['statistic_code']);
        $p = $this->proxy((string) $s['proxy_code']);
        $of = 'the ' . $p['measure'] . ' of ' . ($p['segment'] ?? 'the whole book');

        if (preg_match('/insufficient overlap \(n=(\d+)\)/', $reason, $m)) {
            return (int) $m[1] === 0
                ? "{$driver} and {$of} have no months of data in common, so the pair cannot be tested."
                : "{$driver} and {$of} share only {$m[1]} months of data, too few to test.";
        }

        $lag = (int) ($s['lag_months'] ?? 0);
        $when = $lag === 0 ? 'in the same month' : ($lag === 1 ? '1 month later' : "{$lag} months later");
        $r2 = $s['r_squared'] !== null ? (float) $s['r_squared'] : (preg_match('/R2 ([\d.]+)/', $reason, $m) ? (float) $m[1] : null);
        $out = $r2 !== null
            ? $driver . ' explains ' . round($r2 * 100) . '% of the movement in ' . $of . ', ' . $when
            : 'The link between ' . (preg_match('/^[A-Z][a-z]/', $driver) ? lcfirst($driver) : $driver) . ' and ' . $of . ' cannot be measured yet' . ($lag === 0 ? '' : " at a {$lag}-month lag");
        if (preg_match('/(\d+) obs (\d{6})-(\d{6})/', $reason, $m)) {
            $out .= ' (' . $m[1] . ' months of data, ' . $this->month($m[2]) . ' to ' . $this->month($m[3]) . ')';
        }
        $out .= '.';

        $notes = [];
        if (preg_match('/correct sign/', $reason)) {
            $notes[] = 'Sign as expected.';
        } elseif (str_contains($reason, 'sign indeterminate')) {
            $notes[] = 'Direction unclear.';
        } elseif (preg_match('/WRONG sign (\w+) vs (\w+)/', $reason, $m)) {
            $notes[] = "Moves the wrong way: {$m[1]}, where {$m[2]} is expected.";
        }
        if (str_contains($reason, '(<min)')) {
            $notes[] = 'Fewer months than the minimum for a model.';
        }
        if (preg_match('/p=([\d.]+)>/', $reason, $m)) {
            $notes[] = 'Not statistically significant.';
        }
        if (preg_match('/R2 [\d.]+</', $reason)) {
            $notes[] = 'Too weak to use.';
        }
        if (str_contains($reason, 'disagree')) {
            $notes[] = 'The two correlation tests disagree.';
        }
        if (str_contains($reason, 'no forward path')) {
            $notes[] = 'No forecast for this series, so scenarios cannot move it.';
        }
        if (preg_match('/spans ([A-Z0-9_]+)/', $reason, $m)) {
            $event = $this->events[$m[1]] ?? str_replace('_', ' ', $m[1]);
            $notes[] = 'The data runs across the ' . preg_replace('/\s*\(.*\)$/', '', $event) . '.';
        }

        return trim($out . ' ' . implode(' ', $notes));
    }

    /** A guardrail decline code in words. */
    public function declined(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return [
            'insufficient_n' => 'Too few months of data',
            'wrong_sign' => 'Moves the wrong way',
            'r2<cutoff' => 'R² below the cut-off',
            'insignificant' => 'Not statistically significant',
        ][$code] ?? str_replace('_', ' ', $code);
    }
}
