<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 100px 34px 60px 34px; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 9.5px; color: #1f2937; margin: 0; }

    .hdr { position: fixed; top: -75px; left: 0; right: 0; height: 70px; }
    .hdr .co { font-size: 17px; font-weight: bold; color: #14532d; }
    .hdr .ti { font-size: 12px; color: #16a34a; margin-top: 2px; }
    .hdr .ba { height: 4px; background: #16a34a; margin-top: 7px; }

    .ftr { position: fixed; bottom: -42px; left: 0; right: 0; height: 30px;
           font-size: 8px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 6px; }
    /* page numbers are stamped by App\Support\PdfPageNumbers after layout */

    .cover { text-align: center; padding-top: 170px; }
    .cover h1 { font-size: 30px; color: #14532d; margin: 0 0 6px 0; }
    .cover h2 { font-size: 14px; color: #16a34a; font-weight: normal; margin: 0 0 20px 0; }
    .cover .meta { font-size: 10px; color: #6b7280; }
    .cover .bar { width: 220px; height: 5px; margin: 26px auto; }

    table.control { border-collapse: collapse; margin: 30px auto 0 auto; font-size: 9.5px; width: 70%; }
    table.control td { border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; }
    table.control td.k { background: #f0fdf4; color: #14532d; font-weight: bold; width: 35%; }

    .toc h2 { font-size: 15px; color: #14532d; border-left: 4px solid #16a34a; padding-left: 8px; }
    .toc .chap { font-weight: bold; color: #14532d; margin-top: 8px; font-size: 10.5px; }
    .toc .sec { margin-left: 14px; color: #374151; font-size: 9.5px; padding: 1.5px 0; }

    .chapter { page-break-before: always; }
    .chapter h2 { font-size: 15px; color: #14532d; border-left: 4px solid #16a34a; padding-left: 8px; margin: 0 0 12px 0; }
    .chapter h3 { font-size: 11.5px; color: #166534; margin: 14px 0 5px 0; }
    .chapter h4 { font-size: 10px; color: #1f2937; margin: 10px 0 4px 0; }
    .chapter p { margin: 0 0 6px 0; line-height: 1.5; }
    .chapter ul, .chapter ol { margin: 0 0 6px 16px; padding: 0; }
    .chapter li { margin-bottom: 2px; }
    .chapter table { border-collapse: collapse; width: 100%; margin: 6px 0 10px 0; font-size: 8.5px; page-break-inside: auto; }
    .chapter th { background: #15803d; color: #fff; text-align: left; padding: 4px 6px; font-size: 8px; text-transform: uppercase; }
    .chapter td { border-bottom: 1px solid #e5e7eb; padding: 3.5px 6px; vertical-align: top; }
    .chapter tr:nth-child(even) td { background: #f9fafb; }
    .chapter code { font-family: DejaVu Sans Mono, monospace; font-size: 8.5px; color: #92400e; }
    .chapter pre { background: #f8fafc; border-left: 3px solid #f59e0b; padding: 6px 8px; font-size: 8px;
                   white-space: pre-wrap; word-wrap: break-word; margin: 6px 0 10px 0; }
    .chapter pre code { color: #1f2937; }
    .chapter blockquote { border-left: 3px solid #d1d5db; margin: 6px 0; padding: 2px 10px; color: #4b5563; }

    .schema h2 { font-size: 15px; color: #14532d; border-left: 4px solid #16a34a; padding-left: 8px; margin: 0 0 8px 0; }
    .schema .tbl { margin: 0 0 10px 0; page-break-inside: avoid; }
    .schema .tbl .name { font-weight: bold; color: #14532d; font-size: 9.5px; margin: 8px 0 2px 0; }
    .schema table { border-collapse: collapse; width: 100%; font-size: 7.8px; }
    .schema th { background: #ecfdf5; color: #14532d; text-align: left; padding: 2.5px 5px; }
    .schema td { border-bottom: 1px solid #f1f5f9; padding: 2px 5px; }
</style>
</head>
<body>
    <div class="hdr">
        <div class="co">{{ $company }}</div>
        <div class="ti">IFRS 9 ECL &amp; EIR Platform: {{ $title }}</div>
        <div class="ba"></div>
    </div>

    <div class="ftr">
        <table style="width:100%"><tr>
            <td>{{ $company }} IFRS 9 {{ $title }}</td>
            <td style="text-align:center">v{{ $front['version'] }} &middot; {{ $front['classification'] }} &middot; revised {{ $last_revised ?? $generated_at }}</td>
            <td style="text-align:right" class="pg"></td>
        </tr></table>
    </div>

    {{-- Cover, document control, how to use, role map (shared front matter) --}}
    @include('manual._front_matter', ['front' => $front])

    {{-- Table of contents --}}
    <div class="toc" style="page-break-before: always;">
        <h2>Contents</h2>
        @foreach ($chapters as $c)
            <div class="chap">{{ $c['title'] }}</div>
            @foreach ($c['sections'] as $s)
                <div class="sec">{{ $s['title'] }}</div>
            @endforeach
        @endforeach
        @if (count($schema))
            <div class="chap">Appendix A. Live database schema</div>
        @endif
    </div>

    {{-- Chapters --}}
    @foreach ($chapters as $c)
        <div class="chapter">{!! $c['html'] !!}</div>
    @endforeach

    {{-- Live schema appendix --}}
    @if (count($schema))
        <div class="schema" style="page-break-before: always;">
            <h2>Appendix A. Live database schema</h2>
            <p style="font-size:9px; color:#6b7280;">{{ count($schema) }} tables read from the connected database on {{ $generated_at }}. Row counts are as at generation.</p>
            @foreach ($schema as $t)
                <div class="tbl">
                    <div class="name">{{ $t['name'] }} <span style="font-weight:normal; color:#6b7280;">({{ count($t['columns']) }} columns, {{ number_format($t['row_count']) }} rows)</span></div>
                    <table>
                        <tr><th style="width:38%">Column</th><th style="width:32%">Type</th><th>Key</th><th>Nullable</th></tr>
                        @foreach ($t['columns'] as $col)
                            <tr>
                                <td>{{ $col['name'] }}</td>
                                <td>{{ $col['type'] }}</td>
                                <td>{{ $col['key'] === 'PRI' ? 'primary' : ($col['key'] === 'MUL' ? 'index' : ($col['key'] === 'UNI' ? 'unique' : '')) }}</td>
                                <td>{{ $col['nullable'] ? 'yes' : '' }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endforeach
        </div>
    @endif
</body>
</html>
