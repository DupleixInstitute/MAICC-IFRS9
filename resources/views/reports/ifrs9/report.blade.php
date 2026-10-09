{{--
    The one MAIIC report PDF (DomPDF, A4). Every report in the hub, and the
    EIR, RBM, reconciliation and stress downloads, render through this view
    from the same payload: company, title, subtitle, period, generated_at,
    generated_by, kpis[{label, value, tone}], sections[{heading, columns,
    align, rows, note?}], notes[]. "Page N of M" is stamped after layout by
    App\Support\ReportDownload::stampPageNumbers.
--}}
@php
    $logo = $logo ?? (is_file(public_path('images/maiic-logo.png')) ? public_path('images/maiic-logo.png') : null);
    $periodLabel = $periodLabel ?? ($report['period'] ?? '');
    $toneColour = ['maiic' => '#15803d', 'emerald' => '#15803d', 'rose' => '#dc2626', 'amber' => '#d97706', 'grey' => '#6b7280'];
    $isTotal = fn ($row) => is_array($row) && isset(array_values($row)[0]) && preg_match('/^(total|totals|grand total|net|closing)/i', trim((string) array_values($row)[0]));
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $report['company'] }} - {{ $report['title'] }}</title>
<style>
    @page { margin: 96px 28px 58px 28px; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 8.5px; color: #1f2937; margin: 0; line-height: 1.35; }

    .hdr { position: fixed; top: -80px; left: 0; right: 0; height: 72px; }
    .hdr table { width: 100%; border-collapse: collapse; }
    .hdr td { padding: 0; vertical-align: bottom; }
    .hdr .co { font-size: 9px; font-weight: bold; color: #15803d; text-transform: uppercase; letter-spacing: .6px; }
    .hdr .ti { font-size: 15px; font-weight: bold; color: #14532d; margin-top: 1px; }
    .hdr .su { font-size: 8px; color: #6b7280; margin-top: 2px; }
    .hdr .meta { text-align: right; font-size: 8px; color: #4b5563; line-height: 1.5; }
    .hdr .meta b { color: #14532d; }
    .hdr .logo { height: 40px; margin-bottom: 3px; }
    .bar { margin-top: 6px; }
    .bar td { height: 3px; padding: 0; line-height: 0; font-size: 0; }

    .ftr { position: fixed; bottom: -38px; left: 0; right: 0; height: 22px;
           font-size: 7.5px; color: #6b7280; border-top: 0.5px solid #d1d5db; padding-top: 5px; }

    table.kpis { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px -6px; }
    table.kpis td { border: 0.75px solid #e5e7eb; padding: 6px 8px; vertical-align: top; background: #ffffff; }
    .k-l { font-size: 6.5px; font-weight: bold; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; }
    .k-v { font-size: 12.5px; font-weight: bold; color: #111827; margin-top: 2px; }
    .k-s { font-size: 6.5px; color: #9ca3af; margin-top: 1px; }

    .sec { margin-bottom: 12px; }
    .part { margin: 2px 0 8px 0; padding: 5px 8px; background: #14532d; color: #ffffff; }
    .part .pt { font-size: 11px; font-weight: bold; }
    .part .pn { font-size: 7.5px; color: #d1fae5; margin-top: 1px; }
    .sec h3 { font-size: 10px; color: #14532d; margin: 0 0 4px 0; padding-left: 6px; border-left: 3px solid #15803d; }
    .sec .note { font-size: 7.5px; color: #6b7280; margin: 0 0 4px 0; }

    table.grid { width: 100%; border-collapse: collapse; }
    table.grid thead { display: table-header-group; }
    table.grid tr { page-break-inside: avoid; }
    table.grid th { background: #14532d; color: #ffffff; font-size: 7px; font-weight: bold; padding: 4px 6px;
                    text-align: left; text-transform: uppercase; letter-spacing: .3px; }
    table.grid th.r { text-align: right; }
    table.grid td { padding: 3.5px 6px; font-size: 8px; border-bottom: 0.5px solid #e5e7eb; vertical-align: top; }
    table.grid td.r { text-align: right; white-space: nowrap; }
    table.grid tr.z td { background: #f3faf5; }
    table.grid tr.tot td { font-weight: bold; background: #fdf6e7; border-top: 0.75px solid #14532d; border-bottom: 1.5px solid #14532d; }
    .empty { color: #9ca3af; font-style: italic; padding: 6px 0; }
    .notes { margin-top: 8px; border-left: 2.5px solid #d97706; background: #fffbeb; padding: 5px 8px; font-size: 7.5px; color: #4b5563; }
    .notes p { margin: 0 0 2px 0; }
</style>
</head>
<body>
    <div class="hdr">
        <table><tr>
            @if($logo)
                <td style="width:120px"><img class="logo" src="{{ $logo }}" alt="MAIIC"></td>
            @endif
            <td>
                <div class="co">{{ $report['company'] }}</div>
                <div class="ti">{{ $report['title'] }}</div>
                @if(!empty($report['subtitle']))<div class="su">{{ $report['subtitle'] }}</div>@endif
            </td>
            <td class="meta" style="width:190px">
                @if(!empty($report['period']))Reporting period: <b>{{ $periodLabel }}</b><br>@endif
                Prepared on: <b>{{ $report['generated_at'] }}</b>
                @if(!empty($report['generated_by']))<br>Prepared by: {{ $report['generated_by'] }}@endif
            </td>
        </tr></table>
        <table class="bar"><tr>
            <td style="width:60%; background:#15803d"></td>
            <td style="width:25%; background:#d97706"></td>
            <td style="width:15%; background:#dc2626"></td>
        </tr></table>
    </div>

    <div class="ftr">
        {{ $report['company'] }} IFRS 9 ECL and EIR system &middot; {{ $report['title'] }}@if(!empty($report['period'])) &middot; {{ $periodLabel }}@endif @if(!empty($report['currency']))&middot; Amounts in {{ $report['currency'] }} @endif&middot; Confidential, internal use only
    </div>

    @if(!empty($report['kpis']))
        <table class="kpis"><tr>
            @foreach($report['kpis'] as $k)
                <td style="width: {{ intval(100 / max(1, count($report['kpis']))) }}%; border-left: 3px solid {{ $toneColour[$k['tone'] ?? 'maiic'] ?? '#15803d' }}">
                    <div class="k-l">{{ $k['label'] }}</div>
                    <div class="k-v">{{ $k['value'] }}</div>
                    @if(!empty($k['sub']))<div class="k-s">{{ $k['sub'] }}</div>@endif
                </td>
            @endforeach
        </tr></table>
    @endif

    @php $part = null; @endphp
    @forelse($report['sections'] as $sec)
        @if(!empty($sec['part']) && $sec['part'] !== $part)
            @php $first = $part === null; $part = $sec['part']; @endphp
            <div class="part">
                <div class="pt">{{ $part }}</div>
                @if(!empty($sec['part_note']))<div class="pn">{{ $sec['part_note'] }}</div>@endif
            </div>
        @endif
        <div class="sec">
            @if(!empty($sec['heading']))<h3>{{ $sec['heading'] }}</h3>@endif
            @if(!empty($sec['note']))<p class="note">{{ $sec['note'] }}</p>@endif
            @if(empty($sec['rows']))
                <div class="empty">No data for this section in the selected period.</div>
            @else
                <table class="grid">
                    <thead><tr>
                        @foreach($sec['columns'] as $i => $col)
                            <th class="{{ ($sec['align'][$i] ?? 'l') === 'r' ? 'r' : '' }}">{{ $col }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                        @foreach($sec['rows'] as $ri => $row)
                            <tr class="{{ $isTotal($row) ? 'tot' : ($ri % 2 ? 'z' : '') }}">
                                @foreach(array_values($row) as $i => $cell)
                                    <td class="{{ ($sec['align'][$i] ?? 'l') === 'r' ? 'r' : '' }}">{{ $cell }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @empty
        <div class="empty">This report has no content for the selected period.</div>
    @endforelse

    @if(!empty($report['notes']))
        <div class="notes">
            @foreach($report['notes'] as $n)<p>{{ $n }}</p>@endforeach
        </div>
    @endif
</body>
</html>
