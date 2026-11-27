{{--
    Shared frame for every printed document (mPDF): letterhead, document title + reference block, a footer
    with the business details and page numbers. Child views fill @section('body').
    mPDF supports tables and simple CSS only (no flex/grid): layout is done with tables.

    Expects: $dir ('rtl'|'ltr'), $docTitle, $docMeta (label => value), and the business keys from
    OrderDocuments::business(): $businessName, $registration, $address, $phone, $email, $logo, $footer.
--}}
@php
    $ink = '#2b211c'; $muted = '#7d6e64'; $accent = '#9a4a24'; $gold = '#c9a24d'; $line = '#e8ddd3'; $soft = '#faf6f2';
    $start = $dir === 'rtl' ? 'right' : 'left';
    $end = $dir === 'rtl' ? 'left' : 'right';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $dir }}">
<head>
<meta charset="utf-8">
<style>
    @page { margin-top: 12mm; margin-bottom: 22mm; margin-header: 0; margin-footer: 7mm; footer: html_docfooter; }
    body { font-family: {{ $fontFamily ?? 'xbriyaz' }}, xbriyaz, dejavusans; color: {{ $ink }}; font-size: 9.5pt; line-height: 1.45; }
    table { border-collapse: collapse; width: 100%; }
    .muted { color: {{ $muted }}; }
    .num { font-family: dejavusans; }
    .label { color: {{ $muted }}; font-size: 7.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px; }
    table.card { border: 1px solid {{ $line }}; width: 100%; }
    td.card-head { background: {{ $soft }}; border-bottom: 1px solid {{ $line }}; padding: 2mm 3mm; font-size: 8pt; font-weight: bold; color: {{ $accent }}; }
    td.card-body { padding: 2.5mm 3mm; vertical-align: top; }
    .grid th { background: {{ $ink }}; color: #fff; font-size: 8pt; font-weight: bold; padding: 2.4mm 2mm; }
    .grid td { padding: 2.2mm 2mm; border-bottom: 1px solid {{ $line }}; vertical-align: top; }
    .grid tr.alt td { background: #fcfaf8; }
    .strip td { border: 1px solid {{ $line }}; padding: 1.8mm 2.5mm; }
    .total-row td { background: {{ $ink }}; color: #fff; font-weight: bold; padding: 2.6mm 3mm; }
    .sign { border-top: 1px solid {{ $ink }}; padding-top: 1.5mm; text-align: center; color: {{ $muted }}; font-size: 8pt; }
</style>
</head>
<body>

<htmlpagefooter name="docfooter">
    <table style="border-top: 1.5px solid {{ $accent }};">
        <tr>
            <td style="padding-top: 2mm; font-size: 7.5pt; color: {{ $muted }}; text-align: {{ $start }};">
                <b style="color: {{ $ink }};">{{ $businessName }}</b>
                @if ($phone) · <bdo dir="ltr" class="num">{{ $phone }}</bdo>@endif
                @if ($email) · <bdo dir="ltr" class="num">{{ $email }}</bdo>@endif
                @if ($footer)<br>{{ $footer }}@endif
            </td>
            <td style="padding-top: 2mm; font-size: 7.5pt; color: {{ $muted }}; text-align: {{ $end }}; width: 22%;" class="num">{PAGENO} / {nbpg}</td>
        </tr>
    </table>
</htmlpagefooter>

{{-- Letterhead --}}
<table>
    <tr>
        <td style="vertical-align: middle; text-align: {{ $start }}; width: 58%;">
            <table style="width: auto;">
                <tr>
                    @if ($logo)
                        <td style="vertical-align: middle; padding-{{ $end }}: 3mm;"><img src="{{ $logo }}" style="height: 19mm;" alt=""></td>
                    @endif
                    <td style="vertical-align: middle; text-align: {{ $start }};">
                        <div style="font-size: 15pt; font-weight: bold;">{{ $businessName }}</div>
                        @if ($registration)<div class="muted" style="font-size: 8.5pt;">{{ __('invoices.registration') }}: <bdo dir="ltr" class="num">{{ $registration }}</bdo></div>@endif
                        @if ($address)<div class="muted" style="font-size: 8.5pt;">{{ $address }}</div>@endif
                        @if ($phone)<div class="muted" style="font-size: 8.5pt;">{{ __('invoices.phone') }}: <bdo dir="ltr" class="num">{{ $phone }}</bdo></div>@endif
                    </td>
                </tr>
            </table>
        </td>
        <td style="vertical-align: top; text-align: {{ $end }}; width: 42%;">
            <div style="font-size: 21pt; font-weight: bold; color: {{ $accent }}; line-height: 1.1;">{{ $docTitle }}</div>
            <table style="margin-top: 2mm; width: auto;" align="{{ $end }}">
                @foreach ($docMeta as $label => $value)
                    <tr>
                        <td class="muted" style="padding: 0.6mm 2mm; font-size: 8.5pt; text-align: {{ $start }};">{{ $label }}</td>
                        <td style="padding: 0.6mm 2mm; font-weight: bold; text-align: {{ $start }};"><bdo dir="ltr" class="num">{{ $value }}</bdo></td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>
<div style="height: 2px; background: {{ $accent }}; margin-top: 4mm;"></div>
<div style="height: 1px; background: {{ $gold }}; margin-top: 0.6mm; margin-bottom: 5mm;"></div>

@yield('body')

</body>
</html>
