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
        <p class="cap" style="margin-top:3px">Ran under the {{ strtolower($lin['route']) }} route{{ $lin['method'] ? ' (' . strtolower($lin['method']) . ')' : '' }}@if ($lin['fit']); fit {{ $lin['fit']['id'] }}{{ ! empty($lin['fit']['relationship']) ? ', ' . $lin['fit']['relationship'] : '' }}{{ ! empty($lin['fit']['status']) ? ', ' . strtolower($lin['fit']['status']) : '' }}@endif @if ($lin['set']); scenario set {{ $lin['set']['id'] }}{{ ! empty($lin['set']['name']) ? ', ' . $lin['set']['name'] : '' }}{{ ! empty($lin['set']['status']) ? ', ' . strtolower($lin['set']['status']) : '' }}@endif.</p>
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

