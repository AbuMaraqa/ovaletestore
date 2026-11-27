{{-- 10×15 cm thermal shipping labels (mPDF), one page per order ($labels). Big, high-contrast: recipient, phone, city, amount to collect, barcode. --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<style>
    body { font-family: {{ $fontFamily ?? 'xbriyaz' }}, xbriyaz, dejavusans; color: #000; font-size: 9pt; }
    table { border-collapse: collapse; width: 100%; }
    .box { border: 1.2px solid #000; padding: 2.2mm 2.6mm; }
    .label { font-size: 7.5pt; font-weight: bold; color: #333; }
    .num { font-family: dejavusans; }
</style>
</head>
<body>
@foreach ($labels as $i => ['order' => $order, 'pieces' => $pieces, 'collect' => $collect])
@if ($i > 0)<pagebreak />@endif

{{-- Sender --}}
<table class="box">
    <tr>
        <td style="vertical-align: middle; text-align: right;">
            <div class="label">{{ __('invoices.label_from') }}</div>
            <div style="font-size: 10.5pt; font-weight: bold;">{{ $businessName }}</div>
            @if ($phone)<div style="text-align: right;"><bdo dir="ltr" class="num">{{ $phone }}</bdo></div>@endif
        </td>
        @if ($logo)
            <td style="width: 16mm; text-align: left; vertical-align: middle;"><img src="{{ $logo }}" style="height: 14mm;" alt=""></td>
        @endif
    </tr>
</table>

{{-- Recipient --}}
<div class="box" style="margin-top: 2mm; border-width: 2px;">
    <div class="label">{{ __('invoices.label_to') }}</div>
    <div style="font-size: 15pt; font-weight: bold; margin-top: 0.5mm;">{{ $order->customer_name }}</div>
    @if ($order->customer_phone)
        <div style="font-size: 15pt; font-weight: bold; text-align: right;"><bdo dir="ltr" class="num">{{ $order->customer_phone }}</bdo></div>
    @endif
    @if ($order->shipping_city)
        <div style="font-size: 13pt; font-weight: bold; margin-top: 1mm;">{{ $order->shipping_city }}</div>
    @endif
    @if ($order->shipping_address)
        <div style="font-size: 10pt;">{{ $order->shipping_address }}</div>
    @endif
    <div style="margin-top: 1mm; font-size: 8.5pt;">{{ $order->shipping_method_name }}</div>
</div>

{{-- Amount to collect / paid --}}
<table style="margin-top: 2mm;">
    <tr>
        <td class="box" style="width: 60%; text-align: right; background: {{ $collect ? '#000' : '#fff' }}; color: {{ $collect ? '#fff' : '#000' }};">
            <div style="font-size: 7.5pt; font-weight: bold;">{{ $collect ? __('invoices.label_collect') : __('invoices.label_no_collect') }}</div>
            <div class="num" style="font-size: 16pt; font-weight: bold; text-align: right;">{{ $collect ?? '—' }}</div>
        </td>
        <td class="box" style="width: 40%; text-align: center;">
            <div class="label">{{ __('invoices.label_pieces') }}</div>
            <div style="font-size: 16pt; font-weight: bold;">{{ $pieces }}</div>
        </td>
    </tr>
</table>

{{-- Order number as a scannable barcode --}}
<div class="box" style="margin-top: 2mm; text-align: center;">
    <barcode code="{{ $order->number }}" type="C128B" size="1.1" height="1.4" />
    <div class="num" style="font-size: 11pt; font-weight: bold; margin-top: 0.8mm;">{{ $order->number }}</div>
    <div style="font-size: 7.5pt;">{{ $order->placed_at->format('Y-m-d') }}</div>
</div>

@if ($order->customer_note)
    <div class="box" style="margin-top: 2mm; font-size: 8pt;"><b>{{ __('invoices.note') }}:</b> {{ \Illuminate\Support\Str::limit($order->customer_note, 160) }}</div>
@endif

@endforeach
</body>
</html>
