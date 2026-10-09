<?php

namespace App\Support;

/**
 * Small server-side charts for DomPDF, which cannot run JavaScript. Each
 * method returns an SVG as a data URI for an <img> tag. Colours follow the
 * MAIIC dashboard: Stage 1 green, Stage 2 gold, Stage 3 red, coverage
 * line deep MAIIC green.
 */
class PdfCharts
{
    public const STAGE_COLOURS = ['#16a34a', '#f59e0b', '#dc2626'];
    public const LINE = '#14532d';
    public const GRID = '#e5e7eb';
    public const TEXT = '#4b5563';

    /**
     * ECL by stage as stacked columns per month, with ECL coverage (%) as a
     * line on the right-hand axis. The selected month is marked.
     *
     * @param array<int,array{period:string,ecl_by_stage:array,ecl_percentage:float|int}> $trend
     */
    public static function trend(array $trend, ?string $selected, int $width = 700, int $height = 230): string
    {
        $left = 52;
        $right = 44;
        $top = 14;
        $bottom = 30;
        $plotW = $width - $left - $right;
        $plotH = $height - $top - $bottom;
        $n = max(1, count($trend));

        $maxEcl = 0.0;
        $maxCov = 0.0;
        foreach ($trend as $t) {
            $maxEcl = max($maxEcl, array_sum(array_map('floatval', $t['ecl_by_stage'])));
            $maxCov = max($maxCov, (float) $t['ecl_percentage']);
        }
        $eclTop = self::niceCeil($maxEcl);
        $covTop = self::niceCeil($maxCov * 1.15 ?: 10);

        $svg = [];
        // Grid and left axis (ECL), right axis (coverage %).
        for ($i = 0; $i <= 4; $i++) {
            $y = $top + $plotH - $plotH * $i / 4;
            $svg[] = sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke="%s" stroke-width="0.6"/>', $left, $y, $left + $plotW, $y, self::GRID);
            $svg[] = sprintf('<text x="%d" y="%.1f" font-size="8" text-anchor="end" fill="%s" font-family="Helvetica">%s</text>', $left - 5, $y + 3, self::TEXT, self::compact($eclTop * $i / 4));
            $svg[] = sprintf('<text x="%d" y="%.1f" font-size="8" fill="%s" font-family="Helvetica">%s%%</text>', $left + $plotW + 5, $y + 3, self::LINE, self::trim($covTop * $i / 4));
        }

        $slot = $plotW / $n;
        $barW = min(34, $slot * 0.62);
        $points = [];
        foreach (array_values($trend) as $i => $t) {
            $x = $left + $slot * $i + ($slot - $barW) / 2;
            $base = $top + $plotH;
            foreach (array_values($t['ecl_by_stage']) as $s => $v) {
                $h = $eclTop > 0 ? $plotH * (float) $v / $eclTop : 0;
                if ($h > 0) {
                    $svg[] = sprintf('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="%s"/>', $x, $base - $h, $barW, $h, self::STAGE_COLOURS[$s] ?? '#999999');
                }
                $base -= $h;
            }
            $cx = $left + $slot * $i + $slot / 2;
            $cy = $top + $plotH - ($covTop > 0 ? $plotH * (float) $t['ecl_percentage'] / $covTop : 0);
            $points[] = [$cx, $cy, $t['period'] === $selected, (float) $t['ecl_percentage']];
            $label = date('M y', strtotime($t['period'] . '-01'));
            $svg[] = sprintf('<text x="%.1f" y="%d" font-size="8" text-anchor="middle" fill="%s" font-family="Helvetica"%s>%s</text>',
                $cx, $height - 14, $t['period'] === $selected ? '#040c04' : self::TEXT, $t['period'] === $selected ? ' font-weight="bold"' : '', $label);
        }
        if (count($points) > 1) {
            $svg[] = sprintf('<polyline points="%s" fill="none" stroke="%s" stroke-width="1.8"/>',
                implode(' ', array_map(fn ($p) => sprintf('%.1f,%.1f', $p[0], $p[1]), $points)), self::LINE);
        }
        foreach ($points as [$cx, $cy, $isSelected, $cov]) {
            $svg[] = sprintf('<circle cx="%.1f" cy="%.1f" r="%.1f" fill="%s" stroke="%s" stroke-width="1.4"/>', $cx, $cy, $isSelected ? 4 : 2.6, $isSelected ? '#fbbf24' : '#ffffff', self::LINE);
            if ($isSelected) {
                $svg[] = sprintf('<text x="%.1f" y="%.1f" font-size="8" font-weight="bold" text-anchor="middle" fill="%s" font-family="Helvetica">%s%%</text>', $cx, $cy - 7, self::LINE, number_format($cov, 2));
            }
        }
        $svg[] = sprintf('<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#9ca3af" stroke-width="0.8"/>', $left, $top + $plotH, $left + $plotW, $top + $plotH);

        return self::uri($width, $height, implode('', $svg));
    }

    /**
     * Two 100% bars, exposure and ECL, split by stage, with the share of
     * each stage written in its segment when it is wide enough.
     *
     * @param array<int,float> $ead by stage
     * @param array<int,float> $ecl by stage
     */
    public static function stageMix(array $ead, array $ecl, int $width = 700, int $height = 74): string
    {
        $left = 64;
        $barW = $width - $left - 8;
        $svg = [];
        foreach ([['Exposure', $ead, 8], ['ECL', $ecl, 40]] as [$label, $values, $y]) {
            $total = array_sum(array_map('floatval', $values));
            $svg[] = sprintf('<text x="0" y="%d" font-size="9" font-weight="bold" fill="#1f2a1e" font-family="Helvetica">%s</text>', $y + 15, $label);
            $x = $left;
            foreach (array_values($values) as $s => $v) {
                $w = $total > 0 ? $barW * (float) $v / $total : 0;
                if ($w <= 0) {
                    continue;
                }
                $svg[] = sprintf('<rect x="%.1f" y="%d" width="%.1f" height="24" fill="%s"/>', $x, $y, $w, self::STAGE_COLOURS[$s]);
                $share = number_format(100 * $v / $total, 1) . '%';
                $text = $w > 90 ? 'Stage ' . ($s + 1) . '  ' . $share : ($w > 34 ? $share : '');
                if ($text !== '') {
                    $svg[] = sprintf('<text x="%.1f" y="%d" font-size="8.5" font-weight="bold" text-anchor="middle" fill="%s" font-family="Helvetica">%s</text>',
                        $x + $w / 2, $y + 15, $s === 1 ? '#3b2a00' : '#ffffff', $text);
                }
                $x += $w;
            }
        }

        return self::uri($width, $height, implode('', $svg));
    }

    private static function uri(int $w, int $h, string $body): string
    {
        $svg = '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '">' . $body . '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /** Round up to 1, 2, 2.5 or 5 times a power of ten. */
    private static function niceCeil(float $v): float
    {
        if ($v <= 0) {
            return 1.0;
        }
        $p = 10 ** floor(log10($v));
        foreach ([1, 2, 2.5, 5, 10] as $m) {
            if ($v <= $m * $p) {
                return $m * $p;
            }
        }

        return 10 * $p;
    }

    private static function compact(float $v): string
    {
        $a = abs($v);
        if ($a >= 1e9) {
            return self::trim($v / 1e9) . 'B';
        }
        if ($a >= 1e6) {
            return self::trim($v / 1e6) . 'M';
        }
        if ($a >= 1e3) {
            return self::trim($v / 1e3) . 'K';
        }

        return self::trim($v);
    }

    private static function trim(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
