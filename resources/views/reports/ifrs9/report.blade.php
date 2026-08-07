<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 110px 28px 60px 28px; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 10px; color: #1f2937; margin: 0; }

    .hdr { position: fixed; top: -85px; left: 0; right: 0; height: 80px; }
    .hdr .co { font-size: 18px; font-weight: bold; color: #14532d; }
    .hdr .ti { font-size: 13px; color: #16a34a; margin-top: 2px; }
    .hdr .su { font-size: 9px; color: #6b7280; margin-top: 2px; }
    .hdr .ba { height: 4px; background: #16a34a; margin-top: 8px; }

    .ftr { position: fixed; bottom: -42px; left: 0; right: 0; height: 30px;
           font-size: 8px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 6px; }
    .ftr .pg:after { content: "Page " counter(page) " of " counter(pages); }

    .kpis { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 16px; }
    .kpi { border-radius: 6px; padding: 10px 12px; color: #fff; }
    .kpi .l { font-size: 8px; text-transform: uppercase; letter-spacing: .5px; opacity: .85; }
    .kpi .v { font-size: 15px; font-weight: bold; margin-top: 3px; }
    .t-maiic   { background: #16a34a; }
    .t-rose    { background: #dc2626; }
    .t-amber   { background: #d97706; }
    .t-emerald { background: #16a34a; }

    .sec { margin-bottom: 18px; }
    .sec h3 { font-size: 12px; color: #14532d; margin: 0 0 6px 0;
              border-left: 4px solid #16a34a; padding-left: 8px; }

    table.grid { width: 100%; border-collapse: collapse; }
    table.grid th { background: #14532d; color: #fff; font-size: 9px; padding: 6px 8px;
                    text-align: left; text-transform: uppercase; letter-spacing: .3px; }
    table.grid td { padding: 5px 8px; font-size: 9px; border-bottom: 1px solid #e5e7eb; }
    table.grid tr:nth-child(even) td { background: #f0fdf4; }
    .r { text-align: right; }
    .empty { color: #9ca3af; font-style: italic; padding: 10px; }
</style>
</head>
<body>
    <div class="hdr">
        <div class="co">{{ $report['company'] }}</div>
        <div class="ti">{{ $report['title'] }}</div>
        <div class="su">{{ $report['subtitle'] }}@if(!empty($report['period'])) &mdash; Reporting Period: {{ $report['period'] }}@endif</div>
        <div class="ba"></div>
    </div>

    <div class="ftr">
        <table style="width:100%"><tr>
            <td>Generated {{ $report['generated_at'] }} &middot; MAIIC IFRS 9 ECL System</td>
            <td style="text-align:right" class="pg"></td>
        </tr></table>
    </div>

    @if(!empty($report['kpis']))
    <table class="kpis"><tr>
        @foreach($report['kpis'] as $k)
            <td class="kpi t-{{ $k['tone'] ?? 'maiic' }}" style="width: {{ intval(100 / max(1, count($report['kpis']))) }}%">
                <div class="l">{{ $k['label'] }}</div>
                <div class="v">{{ $k['value'] }}</div>
            </td>
        @endforeach
    </tr></table>
    @endif

    @forelse($report['sections'] as $sec)
        <div class="sec">
            <h3>{{ $sec['heading'] }}</h3>
            @if(empty($sec['rows']))
                <div class="empty">No data available for this section.</div>
            @else
                <table class="grid">
                    <thead><tr>
                        @foreach($sec['columns'] as $i => $col)
                            <th class="{{ ($sec['align'][$i] ?? 'l') === 'r' ? 'r' : '' }}">{{ $col }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                        @foreach($sec['rows'] as $row)
                            <tr>
                                @foreach($row as $i => $cell)
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
</body>
</html>
