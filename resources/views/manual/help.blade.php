<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 100px 34px 60px 34px; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 10px; color: #1f2937; margin: 0; }

    .hdr { position: fixed; top: -75px; left: 0; right: 0; height: 70px; }
    .hdr .co { font-size: 17px; font-weight: bold; color: #14532d; }
    .hdr .ti { font-size: 12px; color: #16a34a; margin-top: 2px; }
    .hdr .ba { height: 4px; background: #16a34a; margin-top: 7px; }

    .ftr { position: fixed; bottom: -42px; left: 0; right: 0; height: 30px;
           font-size: 8px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 6px; }
    .ftr .pg:after { content: "Page " counter(page) " of " counter(pages); }

    .cover { text-align: center; padding-top: 190px; }
    .cover h1 { font-size: 30px; color: #14532d; margin: 0 0 6px 0; }
    .cover h2 { font-size: 15px; color: #16a34a; font-weight: normal; margin: 0 0 20px 0; }
    .cover .meta { font-size: 10px; color: #6b7280; }
    .cover .bar { width: 220px; height: 5px; margin: 26px auto; }

    .toc h2 { font-size: 15px; color: #14532d; border-left: 4px solid #16a34a; padding-left: 8px; }
    .toc .chap { font-weight: bold; color: #14532d; margin-top: 8px; font-size: 10.5px; }
    .toc .art { margin-left: 14px; color: #374151; font-size: 9.5px; padding: 1.5px 0; }

    h2.chapter { font-size: 15px; color: #14532d; border-left: 4px solid #16a34a;
                 padding-left: 8px; margin: 0 0 12px 0; page-break-before: always; }
    h3.article { font-size: 12px; color: #166534; margin: 14px 0 5px 0; }
    .body-html { font-size: 9.5px; line-height: 1.55; color: #374151; }
    .body-html p { margin: 0 0 6px 0; }
    .body-html ul, .body-html ol { margin: 0 0 6px 16px; padding: 0; }

    table.steps { border-collapse: collapse; margin: 6px 0; }
    table.steps td { font-size: 9.5px; padding: 2.5px 6px 2.5px 0; vertical-align: top; }
    table.steps .n { color: #fff; background: #16a34a; border-radius: 8px; font-weight: bold;
                     text-align: center; width: 15px; font-size: 8.5px; padding: 1px 4px; }

    .fig { margin: 9px 0 4px 0; page-break-inside: avoid; }
    .fig img { width: 100%; border: 1px solid #e5e7eb; }
    .fig .cap { font-size: 8.5px; color: #6b7280; font-style: italic; margin-top: 3px; }
</style>
</head>
<body>
    <div class="hdr">
        <div class="co">{{ $company }}</div>
        <div class="ti">IFRS 9 ECL &amp; EIR Platform: User Manual</div>
        <div class="ba"></div>
    </div>

    <div class="ftr">
        <table style="width:100%"><tr>
            <td>Generated {{ $generated_at }} &middot; MAIIC IFRS 9 ECL System &middot; Confidential</td>
            <td style="text-align:right" class="pg"></td>
        </tr></table>
    </div>

    {{-- Cover --}}
    <div class="cover">
        <h1>User Manual</h1>
        <h2>IFRS 9 Expected Credit Loss &amp; Effective Interest Rate Platform</h2>
        <div class="bar">
            <table style="width:100%; border-collapse:collapse;"><tr>
                <td style="height:5px; background:#16a34a;"></td>
                <td style="height:5px; background:#f59e0b;"></td>
                <td style="height:5px; background:#dc2626;"></td>
            </tr></table>
        </div>
        <div class="meta">{{ $company }} &middot; Prepared by Dupleix Institute &middot; {{ $generated_at }}</div>
    </div>

    {{-- Table of contents --}}
    <div class="toc" style="page-break-before: always;">
        <h2>Contents</h2>
        @foreach ($categories as $ci => $c)
            <div class="chap">{{ $ci + 1 }}. {{ $c['title'] }}</div>
            @foreach ($c['articles'] as $ai => $a)
                <div class="art">{{ $ci + 1 }}.{{ $ai + 1 }} {{ $a['title'] }}</div>
            @endforeach
        @endforeach
    </div>

    {{-- Chapters --}}
    @foreach ($categories as $ci => $c)
        <h2 class="chapter">{{ $ci + 1 }}. {{ $c['title'] }}</h2>
        @foreach ($c['articles'] as $ai => $a)
            <h3 class="article">{{ $ci + 1 }}.{{ $ai + 1 }} {{ $a['title'] }}</h3>
            @if (!empty($a['body']))
                <div class="body-html">{!! $a['body'] !!}</div>
            @endif
            @if (count($a['steps']))
                <table class="steps">
                    @foreach ($a['steps'] as $si => $s)
                        <tr><td class="n">{{ $si + 1 }}</td><td>{{ $s }}</td></tr>
                    @endforeach
                </table>
            @endif
            @foreach ($a['images'] as $img)
                @if (is_file($img['file']))
                    <div class="fig">
                        <img src="{{ $img['file'] }}">
                        <div class="cap">{{ $img['caption'] }}</div>
                    </div>
                @endif
            @endforeach
        @endforeach
    @endforeach
</body>
</html>
