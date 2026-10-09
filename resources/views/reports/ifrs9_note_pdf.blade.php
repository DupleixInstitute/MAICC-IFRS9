{{--
    The IFRS 9 note for the annual financial statements (IFRS 7.35H, 35I,
    35M), A4 portrait, statement style. Built from
    App\Services\Reports\Ifrs9NoteDocuments::build(); "Page N of M" is
    stamped after layout.
--}}
@php
    use App\Services\Reports\Ifrs9NoteDocuments as N;
    $d = $note['unit']['decimals'];
    $cols = [1, 2, 3, 'total'];
    $fmt = fn ($v) => N::amount((int) $v, $d);
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $company }} - IFRS 9 note</title>
<style>
    @page { margin: 92px 40px 60px 40px; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 8.5px; color: #1f2937; margin: 0; line-height: 1.4; }

    .hdr { position: fixed; top: -76px; left: 0; right: 0; height: 66px; }
    .hdr table { width: 100%; border-collapse: collapse; }
    .hdr td { padding: 0; vertical-align: bottom; }
    .hdr .logo { height: 36px; margin-bottom: 3px; }
    .hdr .co { font-size: 8.5px; font-weight: bold; color: #15803d; text-transform: uppercase; letter-spacing: .6px; }
    .hdr .ti { font-size: 13px; font-weight: bold; color: #14532d; margin-top: 1px; }
    .hdr .meta { text-align: right; font-size: 7.5px; color: #4b5563; line-height: 1.5; }
    .hdr .meta b { color: #14532d; }
    .bar td { height: 3px; padding: 0; line-height: 0; font-size: 0; }
    .ftr { position: fixed; bottom: -40px; left: 0; right: 0; height: 22px; font-size: 7.5px; color: #6b7280;
           border-top: 0.5px solid #d1d5db; padding-top: 5px; }

    h1 { font-size: 13px; color: #111827; margin: 0 0 2px 0; }
    .sub { font-size: 8.5px; color: #4b5563; margin: 0 0 10px 0; }
    h2 { font-size: 10px; color: #14532d; margin: 16px 0 3px 0; }
    .cap { font-size: 7.5px; color: #6b7280; margin: 0 0 6px 0; }
    p { margin: 0 0 6px 0; text-align: justify; }

    table.fs { width: 100%; border-collapse: collapse; margin: 2px 0 4px 0; page-break-inside: avoid; }
    table.fs col.lbl { width: 38%; }
    table.fs th { font-size: 7.5px; font-weight: bold; text-align: right; vertical-align: bottom; padding: 2px 4px 3px 4px;
                  border-bottom: 0.75px solid #14532d; color: #111827; }
    table.fs th.l { text-align: left; }
    table.fs th .s { display: block; font-weight: normal; color: #4b5563; font-size: 6.5px; }
    table.fs td { padding: 2.5px 4px; text-align: right; white-space: nowrap; }
    table.fs td.l { text-align: left; white-space: normal; }
    table.fs tr.unit td { font-size: 7px; color: #4b5563; font-style: italic; padding-top: 1px; padding-bottom: 3px; }
    table.fs tr.opening td { font-weight: bold; }
    table.fs tr.closing td { font-weight: bold; border-top: 0.75px solid #14532d; border-bottom: 2.25px double #14532d; }
    table.fs tr.rule td { border-top: 0.75px solid #14532d; }
    table.fs tr.muted td { color: #6b7280; }

    .badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 7px; font-weight: bold; }
    .ok { background: #dcfce7; color: #166534; }
    .warn { background: #fef3c7; color: #92400e; }
    .fail { background: #fee2e2; color: #b91c1c; }
    .info { background: #f3f4f6; color: #374151; }
    .banner { border: 0.75px solid #b91c1c; background: #fee2e2; color: #7f1d1d; padding: 6px 8px; margin: 0 0 10px 0; }
    .note-box { border-left: 2.5px solid #d97706; background: #fffbeb; padding: 5px 8px; margin: 8px 0; font-size: 7.5px; color: #4b5563; }
    table.checks { width: 100%; border-collapse: collapse; }
    table.checks td { padding: 3px 4px; vertical-align: top; border-bottom: 0.5px solid #e5e7eb; font-size: 7.5px; }
    .pb { page-break-before: always; }
</style>
</head>
<body>
<div class="hdr">
    <table><tr>
        @if ($logo)<td style="width:104px"><img class="logo" src="{{ $logo }}" alt="MAIIC"></td>@endif
        <td>
            <div class="co">{{ $company }}</div>
            <div class="ti">IFRS 9 note for the annual financial statements</div>
        </td>
        <td class="meta" style="width:180px">
            Period: <b>{{ $note['opening_label'] }} to {{ $note['closing_label'] }}</b><br>
            Prepared on: <b>{{ $preparedOn }}</b>
            @if ($preparedBy)<br>Prepared by: {{ $preparedBy }}@endif
        </td>
    </tr></table>
    <table class="bar" style="width:100%; border-collapse:collapse; margin-top:6px"><tr>
        <td style="width:60%; background:#15803d"></td><td style="width:25%; background:#d97706"></td><td style="width:15%; background:#dc2626"></td>
    </tr></table>
</div>
<div class="ftr">{{ $company }} IFRS 9 ECL and EIR system &middot; IFRS 9 note &middot; Amounts in {{ $note['unit']['label'] }}</div>

<h1>{{ $note['title'] }}</h1>
<p class="sub">{{ $note['scope'] }}. Movement from {{ $note['opening_label'] }} to {{ $note['closing_label'] }}. Amounts in {{ $note['unit']['label'] }}.</p>

@if ($note['status'] === 'failed')
    <div class="banner"><b>Do not use this note.</b> {{ $note['message'] }}</div>
@endif

@foreach (['ecl' => '(a)', 'gross' => '(b)'] as $key => $letter)
    @php $t = $note['tables'][$key]; @endphp
    <h2>{{ $letter }} {{ $t['title'] }}</h2>
    <p class="cap">{{ $t['caption'] }}</p>
    <table class="fs">
        <col class="lbl"><col><col><col><col>
        <thead><tr>
            <th class="l"></th>
            @foreach (N::STAGE_HEADINGS as $s => [$h, $sub])<th>{{ $h }}<span class="s">{{ $sub }}</span></th>@endforeach
            <th>Total</th>
        </tr></thead>
        <tbody>
            <tr class="unit"><td class="l"></td>@foreach ($cols as $c)<td>{{ $note['unit']['heading'] }}</td>@endforeach</tr>
            @foreach ($t['rows'] as $row)
                <tr class="{{ $row['kind'] }}">
                    <td class="l">{{ $row['label'] }}</td>
                    @foreach ($cols as $c)<td>{{ $fmt($row['values'][$c]) }}</td>@endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@endforeach

@php $pos = $note['position']; @endphp
<div style="page-break-inside: avoid">
<h2>(c) Exposure, loss allowance and coverage by stage</h2>
<p class="cap">Gross carrying amount, loss allowance and coverage at {{ $pos['closing']['label'] }}, with {{ $pos['opening']['label'] }} for comparison (IFRS 7.35M).</p>
<table class="fs">
    <col class="lbl"><col><col><col><col>
    <thead><tr><th class="l"></th>@foreach (N::STAGE_HEADINGS as $s => [$h, $sub])<th>{{ $h }}</th>@endforeach<th>Total</th></tr></thead>
    <tbody>
        @foreach (['closing', 'opening'] as $side)
            @php $p = $pos[$side]; @endphp
            <tr class="opening"><td class="l" colspan="5">{{ $p['label'] }}</td></tr>
            <tr><td class="l">Gross carrying amount ({{ $note['unit']['heading'] }})</td>@foreach ($cols as $c)<td>{{ $fmt($p['gross'][$c]) }}</td>@endforeach</tr>
            <tr><td class="l">Loss allowance ({{ $note['unit']['heading'] }})</td>@foreach ($cols as $c)<td>{{ $fmt(-$p['ecl'][$c]) }}</td>@endforeach</tr>
            <tr class="rule"><td class="l"><b>Net carrying amount ({{ $note['unit']['heading'] }})</b></td>@foreach ($cols as $c)<td><b>{{ $fmt($p['net'][$c]) }}</b></td>@endforeach</tr>
            <tr class="muted"><td class="l">ECL coverage</td>@foreach ($cols as $c)<td>{{ N::percent($p['coverage'][$c]) }}</td>@endforeach</tr>
            <tr class="muted"><td class="l">Share of gross carrying amount</td>@foreach ($cols as $c)<td>{{ N::percent($p['share'][$c]) }}</td>@endforeach</tr>
            <tr class="muted"><td class="l">Number of contracts</td>@foreach ($cols as $c)<td>{{ N::count($p['contracts'][$c]) }}</td>@endforeach</tr>
        @endforeach
    </tbody>
</table>
</div>

<h2>(d) Impairment charge to profit or loss</h2>
<p class="cap">The movement in the loss allowance between {{ $note['opening_label'] }} and {{ $note['closing_label'] }} other than amounts written off.</p>
<table class="fs">
    <col class="lbl"><col><col><col><col>
    <thead><tr><th class="l"></th>@foreach (N::STAGE_HEADINGS as $s => [$h, $sub])<th>{{ $h }}</th>@endforeach<th>Total</th></tr></thead>
    <tbody>
        <tr class="closing"><td class="l">ECL charge / (release) for the period ({{ $note['unit']['heading'] }})</td>@foreach ($cols as $c)<td>{{ $fmt($note['charge'][$c]) }}</td>@endforeach</tr>
    </tbody>
</table>

<h2>(e) Basis of measurement</h2>
@foreach ($note['paragraphs'] as $para)
    <p>{{ $para }}</p>
@endforeach

<div class="pb"></div>
<h1>Workings and checks</h1>
<p class="sub">Supporting information for the preparer and the auditor. Not part of the published note.</p>

@foreach (['ecl' => 'loss allowance', 'gross' => 'gross carrying amount'] as $key => $what)
    @if ($note['tables'][$key]['recon'])
        <h2>Rounding reconciliation: {{ $what }} ({{ $note['unit']['label'] }})</h2>
        <p class="cap">Each figure is rounded on its own. Opening and closing are the loan book figures rounded; the rounding left in a stage column is absorbed in its remeasurement line so the column casts.</p>
        <table class="fs">
            <col class="lbl"><col><col><col><col>
            <thead><tr><th class="l"></th><th>Stage 1</th><th>Stage 2</th><th>Stage 3</th><th>Total</th></tr></thead>
            <tbody>
                @foreach ($note['tables'][$key]['recon'] as $r)
                    <tr class="{{ !empty($r['rule']) ? 'rule' : '' }}">
                        <td class="l">{{ $r['label'] }}</td>
                        @foreach ($cols as $c)<td>{{ $fmt($r['values'][$c]) }}</td>@endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endforeach

<h2>Number of contracts behind each movement</h2>
<table class="fs">
    <col class="lbl"><col><col><col><col>
    <thead><tr><th class="l"></th><th>Stage 1</th><th>Stage 2</th><th>Stage 3</th><th>Total</th></tr></thead>
    <tbody>
        @foreach ($note['tables']['ecl']['rows'] as $row)
            @php $n = $note['contracts'][$row['key']]; @endphp
            <tr class="{{ $row['kind'] === 'closing' ? 'rule' : '' }}">
                <td class="l">{{ $row['label'] }}</td>
                @foreach ($cols as $c)<td>{{ N::count($n[$c]) }}</td>@endforeach
            </tr>
        @endforeach
    </tbody>
</table>
<p class="cap">Transfers and remeasurement count the contracts by their closing stage.</p>

<h2>Checks</h2>
<table class="checks">
    @foreach ($note['checks'] as $c)
        <tr>
            <td style="width:42px"><span class="badge {{ ['pass' => 'ok', 'warn' => 'warn', 'fail' => 'fail', 'info' => 'info'][$c['level']] }}">{{ strtoupper($c['level']) }}</span></td>
            <td style="width:34%"><b>{{ $c['label'] }}</b></td>
            <td>{{ $c['detail'] }}</td>
        </tr>
    @endforeach
</table>

<div class="note-box">
    Source: the loan books (ECL value, carrying amount and stage after the qualitative triggers) for {{ $note['opening_label'] }} and {{ $note['closing_label'] }}, checked against the saved ECL runs. Built {{ $note['built_at'] }}.
</div>
</body>
</html>
