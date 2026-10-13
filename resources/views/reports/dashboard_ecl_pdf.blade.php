{{--
    The IFRS 9 ECL dashboard as a branded A4 portrait PDF
    (DashboardController::eclReportPdf). Same figures as the screen for the
    chosen period, portfolio and compare-to period. "Page N of M" is stamped
    after layout by App\Support\ReportDownload::stampPageNumbers.
--}}
@php
    $label = fn ($p) => $p ? date('F Y', strtotime($p . '-01')) : '';
    $short = fn ($p) => $p ? date('M Y', strtotime($p . '-01')) : '';
    $money = fn ($v) => ($v < 0 ? '(' : '') . number_format(abs((float) $v), 2) . ($v < 0 ? ')' : '');
    $compact = function ($v) use ($currency) {
        $a = abs((float) $v);
        $t = $a >= 1e9 ? number_format($v / 1e9, 2) . 'B' : ($a >= 1e6 ? number_format($v / 1e6, 2) . 'M' : ($a >= 1e3 ? number_format($v / 1e3, 1) . 'K' : number_format($v)));
        return $currency . ' ' . $t;
    };
    $show = fn ($v, $kind) => $kind === 'money' ? $money($v) : ($kind === 'count' ? number_format((float) $v) : number_format((float) $v, 2) . '%');
    $statusClass = ['Favourable' => 'ok', 'Stable' => 'info', 'Watch' => 'warn', 'Adverse' => 'fail'];
    $stageNames = ['Performing', 'Under-performing', 'Credit-impaired'];
    $stageColours = ['#16a34a', '#f59e0b', '#dc2626'];
    $totalEad = array_sum($summary['total_eads']);
    $scope = ($portfolioName ? 'Portfolio ' . $portfolioName : 'All portfolios');
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $company }} - IFRS 9 ECL dashboard {{ $label($period) }}</title>
<style>
    @page { margin: 92px 34px 58px 34px; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 8.5px; color: #1f2937; margin: 0; line-height: 1.35; }

    .hdr { position: fixed; top: -76px; left: 0; right: 0; height: 66px; }
    .hdr table { width: 100%; border-collapse: collapse; }
    .hdr td { padding: 0; vertical-align: bottom; }
    .hdr .logo { height: 38px; margin-bottom: 3px; }
    .hdr .co { font-size: 8.5px; font-weight: bold; color: #15803d; text-transform: uppercase; letter-spacing: .6px; }
    .hdr .ti { font-size: 14px; font-weight: bold; color: #14532d; margin-top: 1px; }
    .hdr .su { font-size: 8px; color: #6b7280; margin-top: 2px; }
    .hdr .meta { text-align: right; font-size: 7.5px; color: #4b5563; line-height: 1.5; }
    .hdr .meta b { color: #14532d; }
    .bar td { height: 3px; padding: 0; line-height: 0; font-size: 0; }
    .ftr { position: fixed; bottom: -38px; left: 0; right: 0; height: 22px; font-size: 7.5px; color: #6b7280;
           border-top: 0.5px solid #d1d5db; padding-top: 5px; }

    h2 { font-size: 10px; color: #14532d; margin: 14px 0 4px 0; padding-left: 6px; border-left: 3px solid #15803d; }
    .cap { font-size: 7.5px; color: #6b7280; margin: 0 0 5px 0; }

    table.kpis { width: 100%; border-collapse: separate; border-spacing: 5px 5px; margin: 0 -5px; }
    table.kpis td { width: 33.3%; border: 0.75px solid #e5e7eb; padding: 6px 8px; vertical-align: top; }
    .k-l { font-size: 6.5px; font-weight: bold; text-transform: uppercase; color: #6b7280; letter-spacing: 0.4px; }
    .k-v { font-size: 14px; font-weight: bold; color: #111827; margin-top: 2px; }
    .k-f { font-size: 7px; color: #9ca3af; }
    .k-d { margin-top: 3px; }

    table.grid { width: 100%; border-collapse: collapse; }
    table.grid tr { page-break-inside: avoid; }
    table.grid th { background: #14532d; color: #ffffff; font-size: 7px; text-transform: uppercase; letter-spacing: 0.3px; padding: 4px 5px; text-align: right; }
    table.grid th.l { text-align: left; }
    table.grid td { padding: 3.5px 5px; border-bottom: 0.5px solid #e5e7eb; text-align: right; white-space: nowrap; }
    table.grid td.l { text-align: left; white-space: normal; }
    table.grid tr.z td { background: #f3faf5; }
    table.grid tr.tot td { font-weight: bold; border-top: 0.75px solid #14532d; border-bottom: 1.5px solid #14532d; background: #fdf6e7; }
    table.grid tr.b td { font-weight: bold; }
    .dot { display: inline-block; width: 7px; height: 7px; border-radius: 4px; margin-right: 4px; }
    .chart { width: 100%; margin: 4px 0 2px 0; }
    .legend { font-size: 7.5px; color: #4b5563; margin-bottom: 6px; }
    .legend span { margin-right: 10px; }
    .badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 7px; font-weight: bold; }
    .ok { background: #dcfce7; color: #166534; }
    .warn { background: #fef3c7; color: #92400e; }
    .fail { background: #fee2e2; color: #b91c1c; }
    .info { background: #f3f4f6; color: #374151; }
    .note { margin-top: 10px; border-left: 2.5px solid #d97706; background: #fffbeb; padding: 5px 8px; font-size: 7.5px; color: #4b5563; }
    .pb { page-break-before: always; }
</style>
</head>
<body>
<div class="hdr">
    <table><tr>
        @if ($logo)<td style="width:112px"><img class="logo" src="{{ $logo }}" alt="MAIIC"></td>@endif
        <td>
            <div class="co">{{ $company }}</div>
            <div class="ti">IFRS 9 expected credit loss dashboard</div>
            <div class="su">{{ $label($period) }}@if ($compare) compared with {{ $label($compare) }}@endif &middot; {{ $scope }}</div>
        </td>
        <td class="meta" style="width:170px">
            Reporting period: <b>{{ $label($period) }}</b><br>
            Prepared on: <b>{{ $preparedOn }}</b>
            @if ($preparedBy)<br>Prepared by: {{ $preparedBy }}@endif
        </td>
    </tr></table>
    <table class="bar" style="width:100%; border-collapse:collapse; margin-top:6px"><tr>
        <td style="width:60%; background:#15803d"></td><td style="width:25%; background:#d97706"></td><td style="width:15%; background:#dc2626"></td>
    </tr></table>
</div>

<div class="ftr">{{ $company }} IFRS 9 ECL and EIR system &middot; ECL dashboard &middot; {{ $label($period) }} &middot; Amounts in {{ $currency }} &middot; Confidential, internal use only</div>

<h2 style="margin-top:0">Headline figures</h2>
<table class="kpis">
    @foreach (array_chunk($kpis, 3) as $row)
        <tr>
            @foreach ($row as $i => $k)
                <td style="border-left: 3px solid {{ ['#16a34a', '#dc2626', '#f59e0b', '#ea580c', '#14532d', '#14532d'][array_search($k['label'], array_column($kpis, 'label'))] ?? '#16a34a' }}">
                    <div class="k-l">{{ $k['label'] }}</div>
                    <div class="k-v">{{ $k['kind'] === 'money' ? $compact($k['value']) : number_format((float) $k['value'], 2) . '%' }}</div>
                    @if ($k['kind'] === 'money')<div class="k-f">{{ $currency }} {{ $money($k['value']) }}</div>@endif
                    <div class="k-d">
                        @if ($k['change'])
                            <span class="badge {{ $statusClass[$k['status']] ?? 'info' }}">{{ $k['up'] === null ? '' : ($k['up'] ? 'Up' : 'Down') }} {{ $k['change'] }} vs {{ $short($compare) }}</span>
                        @elseif (!empty($k['sub']))
                            <span class="k-f">{{ $k['sub'] }}</span>
                        @else
                            <span class="k-f">No comparison</span>
                        @endif
                    </div>
                </td>
            @endforeach
        </tr>
    @endforeach
</table>

<h2>ECL by stage, {{ $label($period) }}</h2>
<table class="grid">
    <thead><tr>
        <th class="l">Stage</th><th>Exposure (EAD)</th><th>ECL</th><th>Coverage</th><th>PD applied</th><th>LGD applied</th><th>Loans</th><th>Share of book</th>
    </tr></thead>
    <tbody>
        @foreach ([0, 1, 2] as $i)
            @php $ead = $summary['total_eads'][$i]; $ecl = $summary['ecl_totals'][$i]; @endphp
            <tr class="{{ $i % 2 ? 'z' : '' }}">
                <td class="l"><span class="dot" style="background: {{ $stageColours[$i] }}"></span>Stage {{ $i + 1 }} <span style="color:#6b7280">{{ $stageNames[$i] }}</span></td>
                <td>{{ $money($ead) }}</td>
                <td>{{ $money($ecl) }}</td>
                <td>{{ $ead ? number_format($ecl / $ead * 100, 2) : '0.00' }}%</td>
                <td>{{ number_format($summary['pd_percentages'][$i], 2) }}%</td>
                <td>{{ number_format($summary['lgd_percentages'][$i], 2) }}%</td>
                <td>{{ number_format($summary['loans_by_stage'][$i]) }}</td>
                <td>{{ $totalEad ? number_format($ead / $totalEad * 100, 1) : '0.0' }}%</td>
            </tr>
        @endforeach
        <tr class="tot">
            <td class="l">Total</td>
            <td>{{ $money($summary['carrying_amount']) }}</td>
            <td>{{ $money($summary['total_ecl']) }}</td>
            <td>{{ number_format($summary['ecl_percentage'], 2) }}%</td>
            <td>{{ number_format($summary['weighted_pd'], 2) }}%</td>
            <td>{{ number_format($summary['weighted_lgd'], 2) }}%</td>
            <td>{{ number_format($summary['total_loans']) }}</td>
            <td>100.0%</td>
        </tr>
    </tbody>
</table>
<p class="cap" style="margin-top:3px">PD and LGD for the total are weighted by exposure, as on the dashboard. Amounts in {{ $currency }}.</p>
<img class="chart" src="{{ $mixChart }}" alt="Stage mix">

<h2>ECL and coverage trend</h2>
<p class="cap">ECL by stage (columns, left axis) and ECL coverage (line, right axis) for the calculated months up to {{ $label($period) }}.</p>
@if ($trendChart)
    <img class="chart" src="{{ $trendChart }}" alt="ECL trend">
    <div class="legend">
        <span><span class="dot" style="background:#16a34a"></span>Stage 1 ECL</span>
        <span><span class="dot" style="background:#f59e0b"></span>Stage 2 ECL</span>
        <span><span class="dot" style="background:#dc2626"></span>Stage 3 ECL</span>
        <span><span class="dot" style="background:#14532d"></span>Coverage %</span>
    </div>
@else
    <p class="cap">No calculated months in this range.</p>
@endif

<div class="pb"></div>
<h2 style="margin-top:0">Trend in figures</h2>
<table class="grid">
    <thead><tr><th class="l">Month</th><th>Exposure (EAD)</th><th>Stage 1 ECL</th><th>Stage 2 ECL</th><th>Stage 3 ECL</th><th>Total ECL</th><th>Coverage</th></tr></thead>
    <tbody>
        @forelse ($trend as $ti => $t)
            <tr class="{{ $t['period'] === $period ? 'b' : '' }} {{ $ti % 2 ? 'z' : '' }}">
                <td class="l">{{ $short($t['period']) }}</td>
                <td>{{ $money($t['total_ead']) }}</td>
                <td>{{ $money($t['ecl_by_stage'][0]) }}</td>
                <td>{{ $money($t['ecl_by_stage'][1]) }}</td>
                <td>{{ $money($t['ecl_by_stage'][2]) }}</td>
                <td>{{ $money($t['total_ecl']) }}</td>
                <td>{{ number_format($t['ecl_percentage'], 2) }}%</td>
            </tr>
        @empty
            <tr><td class="l" colspan="7">No calculated months in this range.</td></tr>
        @endforelse
    </tbody>
</table>

@if (! empty($eclBuildUp))
    @php
        $bu = $eclBuildUp;
        $pctOnPre = fn ($v) => ($bu['pre_fli'] ?? 0) ? (($v > 0 ? '+' : '') . number_format($v / $bu['pre_fli'] * 100, 2) . '%') : '';
        $lin = $bu['lineage'][0] ?? null;
    @endphp
    <h2>ECL build-up, {{ $label($period) }}</h2>
    <p class="cap">From the ECL on the PD before the forward-looking adjustment (FLI) to the booked ECL, read from the saved loan book. Amounts in {{ $currency }}.</p>
    <table class="grid">
        <thead><tr><th class="l">Step</th><th>Amount</th><th>On ECL before FLI</th><th class="l">What it is</th></tr></thead>
        <tbody>
            @if ($bu['available'])
                <tr><td class="l">ECL before FLI</td><td>{{ $money($bu['pre_fli']) }}</td><td></td><td class="l">EAD x PD before FLI over the horizon x LGD</td></tr>
                <tr class="z"><td class="l">Forward-looking effect</td><td>{{ $money($bu['fli_model']) }}</td><td>{{ $pctOnPre($bu['fli_model']) }}</td><td class="l">The macro adjustment to the PD, weighted across the scenario set</td></tr>
                <tr><td class="l">Manual overlays</td><td>{{ $money($bu['overlays']) }}</td><td>{{ $bu['overlays'] ? $pctOnPre($bu['overlays']) : '' }}</td><td class="l">{{ count($bu['overlay_lines']) ? count($bu['overlay_lines']) . ' approved overlay(s) from the register' : 'No approved overlay applied to these loans' }}</td></tr>
            @endif
            <tr class="tot"><td class="l">ECL booked</td><td>{{ $money($bu['final']) }}</td><td></td><td class="l">{{ $bu['ties_to_runs'] ? 'Ties to the total ECL of the saved runs' : 'Differs from the saved runs (' . $money($bu['runs_total']) . ')' }}</td></tr>
        </tbody>
    </table>
    @if (! $bu['available'] || $bu['basis'] === 'pre_fli')
        <p class="cap" style="margin-top:3px">{{ $bu['available'] ? '' : 'The split before and after FLI is not shown: ' }}{{ $bu['reason'] }}</p>
    @endif
    @if ($lin && $lin['route'])
        @php
            $ran = ['the ' . strtolower($lin['route']) . ' route' . ($lin['method'] ? ' (' . strtolower($lin['method']) . ')' : '')];
            if ($lin['fit']) { $ran[] = 'fit ' . $lin['fit']['id'] . (! empty($lin['fit']['relationship']) ? ', ' . $lin['fit']['relationship'] : '') . (! empty($lin['fit']['status']) ? ', ' . strtolower($lin['fit']['status']) : ''); }
            if ($lin['set']) { $ran[] = 'scenario set ' . $lin['set']['id'] . (! empty($lin['set']['name']) ? ', ' . $lin['set']['name'] : '') . (! empty($lin['set']['status']) ? ', ' . strtolower($lin['set']['status']) : ''); }
        @endphp
        <p class="cap" style="margin-top:3px">Ran under {{ implode('; ', $ran) }}.</p>
    @endif
@endif

@if (! empty($eclBreakdown))
    @foreach (['product' => 'ECL by product group', 'sector' => 'ECL by sector', 'rbm' => 'ECL by RBM class'] as $key => $title)
        @php $bd = $eclBreakdown[$key]; @endphp
        <h2>{{ $title }}, {{ $label($period) }}</h2>
        <table class="grid">
            <thead><tr><th class="l">{{ ['product' => 'Product group', 'sector' => 'Sector', 'rbm' => 'RBM class'][$key] }}</th><th>Loans</th><th>Exposure (EAD)</th><th>ECL</th><th>Coverage</th>@if ($key === 'rbm')<th>RBM minimum</th>@endif<th>Share of ECL</th></tr></thead>
            <tbody>
                @foreach ($bd['rows'] as $ri => $r)
                    <tr class="{{ $ri % 2 ? 'z' : '' }}">
                        <td class="l">{{ $r['label'] }}</td>
                        <td>{{ number_format($r['loans']) }}</td>
                        <td>{{ $money($r['ead']) }}</td>
                        <td>{{ $money($r['ecl']) }}</td>
                        <td>{{ $r['coverage'] === null ? '-' : number_format($r['coverage'], 2) . '%' }}</td>
                        @if ($key === 'rbm')<td>{{ number_format(($r['minimum'] ?? 0) * 100, 2) }}%</td>@endif
                        <td>{{ $r['share'] === null ? '-' : number_format($r['share'], 1) . '%' }}</td>
                    </tr>
                @endforeach
                <tr class="tot">
                    <td class="l">Total</td>
                    <td>{{ number_format($bd['total']['loans']) }}</td>
                    <td>{{ $money($bd['total']['ead']) }}</td>
                    <td>{{ $money($bd['total']['ecl']) }}</td>
                    <td>{{ $bd['total']['ead'] ? number_format($bd['total']['ecl'] / $bd['total']['ead'] * 100, 2) . '%' : '-' }}</td>
                    @if ($key === 'rbm')<td></td>@endif
                    <td>100.0%</td>
                </tr>
            </tbody>
        </table>
    @endforeach
    <p class="cap" style="margin-top:3px">Product group and sector show the six largest by ECL and the rest as one line. RBM classes follow the Reserve Bank of Malawi directive's day bands by term. Each total ties to the total ECL above.</p>
@endif

@if ($compareSummary)
    <h2>Portfolio summary against {{ $label($compare) }}</h2>
    <table class="grid">
        <thead><tr><th class="l">Metric</th><th>{{ $short($period) }}</th><th>{{ $short($compare) }}</th><th>Change</th><th style="text-align:center">Status</th></tr></thead>
        <tbody>
            @foreach ($summaryRows as $ri => $r)
                <tr class="{{ $r['bold'] ? 'b' : '' }} {{ $ri % 2 ? 'z' : '' }}">
                    <td class="l">{{ $r['label'] }}</td>
                    <td>{{ $show($r['value'], $r['kind']) }}</td>
                    <td style="color:#6b7280">{{ $r['compare'] === null ? '-' : $show($r['compare'], $r['kind']) }}</td>
                    <td>{{ $r['change'] ?? '-' }}</td>
                    <td style="text-align:center">@if ($r['status'])<span class="badge {{ $statusClass[$r['status']] }}">{{ $r['status'] }}</span>@else - @endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="cap" style="margin-top:3px">Money and counts change in %; rates in percentage points. A rise in exposure is growth; a rise in ECL, PD, LGD or coverage is risk.</p>
@else
    <h2>Portfolio summary</h2>
    <p class="cap">There is no earlier calculated month to compare with.</p>
@endif

<h2>Month-end status, {{ $label($period) }}</h2>
<table class="grid">
    <thead><tr><th class="l">Step</th><th class="l">Status</th><th class="l">Detail</th></tr></thead>
    <tbody>
        <tr><td class="l">Loan book loaded</td><td class="l"><span class="badge {{ ($monthEnd['loan_book_rows'] ?? 0) > 0 ? 'ok' : 'fail' }}">{{ ($monthEnd['loan_book_rows'] ?? 0) > 0 ? 'Done' : 'Not done' }}</span></td><td class="l">{{ number_format($monthEnd['loan_book_rows'] ?? 0) }} loans</td></tr>
        <tr class="z"><td class="l">PD applied</td><td class="l"><span class="badge {{ ($monthEnd['pd_applied'] ?? false) ? 'ok' : 'fail' }}">{{ ($monthEnd['pd_applied'] ?? false) ? 'Done' : 'Not done' }}</span></td><td class="l">{{ ($monthEnd['pd_applied'] ?? false) ? 'Source: ' . ($monthEnd['pd_source'] ?: 'system') : 'Cumulative PD not yet applied' }}</td></tr>
        <tr><td class="l">LGD applied</td><td class="l"><span class="badge {{ ($monthEnd['lgd_applied'] ?? false) ? 'ok' : 'fail' }}">{{ ($monthEnd['lgd_applied'] ?? false) ? 'Done' : 'Not done' }}</span></td><td class="l">{{ ($monthEnd['lgd_applied'] ?? false) ? 'Source: ' . ($monthEnd['lgd_source'] ?: 'system') : 'Cumulative LGD not yet applied' }}</td></tr>
        <tr class="z"><td class="l">ECL calculated</td><td class="l"><span class="badge {{ ($monthEnd['ecl_calculated'] ?? false) ? 'ok' : 'fail' }}">{{ ($monthEnd['ecl_calculated'] ?? false) ? 'Done' : 'Not done' }}</span></td><td class="l">{{ ($monthEnd['ecl_calculated'] ?? false) ? 'Results available' : 'Not yet calculated' }}</td></tr>
    </tbody>
</table>

<div class="note">Figures are the saved ECL runs (expected credit loss by stage) and the loan book the dashboard shows for {{ $label($period) }}{{ $compare ? ' and ' . $label($compare) : '' }}, {{ strtolower($scope) }}. For the movement between the two months by stage, open Reports, Opening to closing ECL.</div>
</body>
</html>
