{{-- Shared front matter for the four System Documentation PDFs: cover,
     document control (with revision history and distribution list),
     how to use, and the role map. Data comes from App\Support\DocumentFrontMatter.
     Rendered by DomPDF, so everything is inline-styled and table-based. --}}
@php
    $C = [
        'green' => '#14532d',
        'accent' => '#16a34a',
        'gold' => '#d97706',
        'ink' => '#1f2937',
        'muted' => '#6b7280',
        'tint' => '#f0fdf4',
        'zebra' => '#f9fafb',
        'navy' => '#2B3990',
        'orange' => '#F58220',
    ];
    $maiicLogo = public_path('images/maiic-logo.png');
    $dupleixLogo = public_path('images/dupleix-institute.png');
    $f = $front;
@endphp

{{-- ============================ COVER ============================ --}}
<div style="page-break-after: always; text-align: center; padding-top: 120px; font-family: DejaVu Sans, sans-serif; color: {{ $C['ink'] }};">
    @if (is_file($maiicLogo))
        <img src="{{ $maiicLogo }}" alt="MAIIC" style="width: 200px; margin: 0 auto 22px auto;">
    @endif
    <div style="font-size: 10px; letter-spacing: 2.5px; text-transform: uppercase; color: {{ $C['muted'] }};">{{ $f['institution'] }}</div>
    <div style="font-size: 27px; font-weight: bold; color: {{ $C['green'] }}; margin-top: 20px; line-height: 1.2;">{{ $f['platform'] }}</div>
    <div style="font-size: 19px; font-weight: bold; margin-top: 6px;">{{ $f['title'] }}</div>
    <table style="margin: 20px auto; border-collapse: collapse;"><tr>
        <td style="width: 70px; height: 4px; background: {{ $C['accent'] }};"></td>
        <td style="width: 30px; height: 4px; background: {{ $C['gold'] }};"></td>
        <td style="width: 20px; height: 4px; background: #dc2626;"></td>
    </tr></table>
    <div style="font-size: 11px; color: {{ $C['muted'] }};">{{ $f['subtitle'] }}</div>

    <table style="margin: 34px auto 0 auto; border-collapse: collapse; font-size: 10.5px; text-align: left;">
        <tr><td style="padding: 3px 14px 3px 0; color: {{ $C['muted'] }};">Version</td><td style="padding: 3px 0; font-weight: bold;">{{ $f['version'] }}</td></tr>
        <tr><td style="padding: 3px 14px 3px 0; color: {{ $C['muted'] }};">Prepared</td><td style="padding: 3px 0; font-weight: bold;">{{ $f['preparedDate'] }}</td></tr>
        <tr><td style="padding: 3px 14px 3px 0; color: {{ $C['muted'] }};">Owner</td><td style="padding: 3px 0; font-weight: bold;">{{ $f['owner'] }}</td></tr>
        <tr><td style="padding: 3px 14px 3px 0; color: {{ $C['muted'] }};">Approved by</td><td style="padding: 3px 0; font-weight: bold;">{{ $f['approvedBy'] }}</td></tr>
        <tr><td style="padding: 3px 14px 3px 0; color: {{ $C['muted'] }};">Classification</td><td style="padding: 3px 0; font-weight: bold; color: {{ $C['gold'] }};">{{ $f['classification'] }}</td></tr>
    </table>

    <div style="margin-top: 36px;">
        @if (is_file($dupleixLogo))
            <img src="{{ $dupleixLogo }}" alt="Dupleix Institute" style="width: 110px; margin: 0 auto 4px auto;">
        @endif
        <div style="font-size: 11px; font-weight: bold; color: {{ $C['muted'] }};">
            System designed &amp; developed by <span style="color: {{ $C['navy'] }}; font-size: 12.5px;">Dupleix Institute</span>:
            Risk <span style="color: {{ $C['orange'] }};">|</span> Strategy <span style="color: {{ $C['orange'] }};">|</span> Data Analytics
        </div>
    </div>
    <div style="margin-top: 26px; font-size: 8.5px; color: {{ $C['muted'] }};">
        This document is confidential and prepared for {{ $f['company'] }} {{ $f['audience'] }}. Do not distribute outside the approved distribution list.
    </div>
</div>

{{-- ===================== DOCUMENT CONTROL ===================== --}}
<div style="page-break-after: always; font-family: DejaVu Sans, sans-serif; color: {{ $C['ink'] }};">
    <h1 style="font-size: 15px; color: {{ $C['green'] }}; border-bottom: 2px solid {{ $C['gold'] }}; padding-bottom: 6px; text-transform: uppercase; letter-spacing: .4px; margin: 0 0 10px 0;">Document control</h1>
    <table style="width: 100%; border-collapse: collapse; font-size: 9.5px;">
        @foreach ([
            ['Document', $f['company'] . ' ' . $f['platform'] . ' ' . $f['title']],
            ['Contract reference', 'Implementation, Licence and Support Agreement (19 August 2026), ' . $f['deliverable']],
            ['Version', $f['version']],
            ['Status', $f['status']],
            ['Prepared date', $f['preparedDate']],
            ['Owner', $f['owner']],
            ['Approved by', $f['approvedBy']],
            ['Classification', $f['classification']],
            ['Developed by', $f['developedBy']],
            ['Applies to', $f['appliesTo']],
            ['Review cycle', $f['reviewCycle']],
            ['Retention', $f['retention']],
        ] as $r)
            <tr>
                <td style="border: 1px solid #d1d5db; padding: 4px 7px; width: 26%; background: {{ $C['tint'] }}; font-weight: bold; color: {{ $C['green'] }};">{{ $r[0] }}</td>
                <td style="border: 1px solid #d1d5db; padding: 4px 7px;">{{ $r[1] }}</td>
            </tr>
        @endforeach
    </table>

    <h2 style="font-size: 11.5px; margin: 18px 0 6px 0;">Revision history</h2>
    <table style="width: 100%; border-collapse: collapse; font-size: 9.5px;">
        <tr>
            <th style="border: 1px solid #d1d5db; padding: 4px 7px; background: {{ $C['tint'] }}; text-align: left; color: {{ $C['green'] }};">Version</th>
            <th style="border: 1px solid #d1d5db; padding: 4px 7px; background: {{ $C['tint'] }}; text-align: left; color: {{ $C['green'] }};">Date</th>
            <th style="border: 1px solid #d1d5db; padding: 4px 7px; background: {{ $C['tint'] }}; text-align: left; color: {{ $C['green'] }};">Author</th>
            <th style="border: 1px solid #d1d5db; padding: 4px 7px; background: {{ $C['tint'] }}; text-align: left; color: {{ $C['green'] }};">Summary of change</th>
        </tr>
        @foreach ($f['revisions'] as $rev)
            <tr>
                <td style="border: 1px solid #d1d5db; padding: 4px 7px;">{{ $rev[0] }}</td>
                <td style="border: 1px solid #d1d5db; padding: 4px 7px;">{{ $rev[1] }}</td>
                <td style="border: 1px solid #d1d5db; padding: 4px 7px;">{{ $rev[2] }}</td>
                <td style="border: 1px solid #d1d5db; padding: 4px 7px;">{{ $rev[3] }}</td>
            </tr>
        @endforeach
    </table>

    <h2 style="font-size: 11.5px; margin: 18px 0 6px 0;">Distribution list</h2>
    <table style="width: 100%; border-collapse: collapse; font-size: 9.5px;">
        <tr>
            <th style="border: 1px solid #d1d5db; padding: 4px 7px; background: {{ $C['tint'] }}; text-align: left; color: {{ $C['green'] }};">Recipient</th>
            <th style="border: 1px solid #d1d5db; padding: 4px 7px; background: {{ $C['tint'] }}; text-align: left; color: {{ $C['green'] }};">Role</th>
            <th style="border: 1px solid #d1d5db; padding: 4px 7px; background: {{ $C['tint'] }}; text-align: left; color: {{ $C['green'] }};">Copy</th>
        </tr>
        @foreach ($f['distribution'] as $d)
            <tr>
                <td style="border: 1px solid #d1d5db; padding: 4px 7px;">{{ $d[0] }}</td>
                <td style="border: 1px solid #d1d5db; padding: 4px 7px;">{{ $d[1] }}</td>
                <td style="border: 1px solid #d1d5db; padding: 4px 7px;">{{ $d[2] }}</td>
            </tr>
        @endforeach
    </table>
</div>

{{-- ===================== HOW TO USE + ROLE MAP ===================== --}}
{{-- No trailing page break: the contents page that follows breaks before itself. --}}
<div style="font-family: DejaVu Sans, sans-serif; color: {{ $C['ink'] }};">
    <h1 style="font-size: 15px; color: {{ $C['green'] }}; border-bottom: 2px solid {{ $C['gold'] }}; padding-bottom: 6px; text-transform: uppercase; letter-spacing: .4px; margin: 0 0 10px 0;">How to use this document</h1>
    <p style="font-size: 10px; line-height: 1.5; margin: 0 0 8px 0;">{{ $f['howToUse'][0] }}</p>
    <ul style="font-size: 10px; line-height: 1.6; margin: 0 0 10px 16px; padding: 0;">
        @foreach ($f['howToUse'][1] as $tip)
            <li>{{ $tip }}</li>
        @endforeach
    </ul>
    <div style="background: {{ $C['tint'] }}; border-left: 4px solid {{ $C['gold'] }}; padding: 8px 11px; font-size: 9.5px; line-height: 1.5;">
        {{ $f['howToUse'][2] }}
    </div>

    <h1 style="font-size: 15px; color: {{ $C['green'] }}; border-bottom: 2px solid {{ $C['gold'] }}; padding-bottom: 6px; text-transform: uppercase; letter-spacing: .4px; margin: 26px 0 10px 0;">Who reads what</h1>
    <table style="width: 100%; border-collapse: collapse; font-size: 9.5px;">
        <tr>
            <th style="border: 1px solid #d1d5db; padding: 5px 7px; background: {{ $C['tint'] }}; text-align: left; width: 28%; color: {{ $C['green'] }};">If you are a...</th>
            <th style="border: 1px solid #d1d5db; padding: 5px 7px; background: {{ $C['tint'] }}; text-align: left; color: {{ $C['green'] }};">Focus on</th>
        </tr>
        @foreach ($f['roles'] as $i => $r)
            <tr style="{{ $i % 2 ? 'background: ' . $C['zebra'] . ';' : '' }}">
                <td style="border: 1px solid #d1d5db; padding: 5px 7px; font-weight: bold; color: {{ $C['green'] }};">{{ $r[0] }}</td>
                <td style="border: 1px solid #d1d5db; padding: 5px 7px;">{{ $r[1] }}</td>
            </tr>
        @endforeach
    </table>
</div>
