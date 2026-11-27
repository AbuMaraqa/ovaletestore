{{--
    Invoice / sales order (same layout, different $docTitle). B2B (company on account) orders get the
    company block, the sales rep, payment terms and signature/stamp boxes.
--}}
@extends('pdf.layout')

@php
    $start = $dir === 'rtl' ? 'right' : 'left';
    $end = $dir === 'rtl' ? 'left' : 'right';
    $isCompany = $order->company_id !== null;
    $pieces = (int) $order->items->sum('quantity');
@endphp

@section('body')
{{-- Seller · customer --}}
<table>
    <tr>
        <td style="width: 49%; vertical-align: top;">
            <table class="card"><tr><td class="card-head">{{ $isCompany ? __('invoices.customer_company') : __('invoices.bill_to') }}</td></tr><tr><td class="card-body" style="text-align: {{ $start }};">
                    <div style="font-size: 11.5pt; font-weight: bold;">{{ $order->customer_name }}</div>
                    @if ($isCompany && $order->company?->contact_name)
                        <div>{{ __('invoices.contact_person') }}: {{ $order->company->contact_name }}</div>
                    @endif
                    @if ($order->customer_phone)<div><bdo dir="ltr" class="num">{{ $order->customer_phone }}</bdo></div>@endif
                    @if ($order->customer_email)<div class="muted"><bdo dir="ltr" class="num">{{ $order->customer_email }}</bdo></div>@endif
                    @if ($order->shipping_city || $order->shipping_address)
                        <div class="muted">{{ collect([$order->shipping_city, $order->shipping_address])->filter()->implode('، ') }}</div>
                    @endif
                </td></tr></table>
        </td>
        <td style="width: 2%;"></td>
        <td style="width: 49%; vertical-align: top;">
            <table class="card"><tr><td class="card-head">{{ __('invoices.terms') }}</td></tr><tr><td class="card-body" style="text-align: {{ $start }};">
                    {{-- Plain lines: a table nested in a bordered cell breaks mPDF's frame. --}}
                    <div><span class="muted">{{ __('invoices.payment') }}:</span> <b>{{ __("admin.pay_{$order->payment_method->value}") }}</b></div>
                    @if ($order->shipping_method_name)
                        <div><span class="muted">{{ __('invoices.delivery') }}:</span> {{ $order->shipping_method_name }}</div>
                    @endif
                    @if ($order->salesRep)
                        <div><span class="muted">{{ __('invoices.sales_rep') }}:</span> {{ $order->salesRep->getAttribute('name') }}</div>
                    @endif
                    <div><span class="muted">{{ __('invoices.currency') }}:</span> <bdo dir="ltr" class="num">{{ $order->currency }}</bdo></div>
                </td></tr></table>
        </td>
    </tr>
</table>

{{-- Items --}}
<table class="grid" style="margin-top: 6mm;">
    <thead>
        <tr>
            <th style="text-align: center; width: 6%;">#</th>
            <th style="text-align: {{ $start }}; width: 17%;">{{ __('invoices.code') }}</th>
            <th style="text-align: {{ $start }};">{{ __('invoices.item') }}</th>
            <th style="text-align: center; width: 9%;">{{ __('invoices.qty') }}</th>
            <th style="text-align: {{ $end }}; width: 15%;">{{ __('invoices.unit_price') }}</th>
            <th style="text-align: {{ $end }}; width: 16%;">{{ __('invoices.line_total') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($order->items as $i => $item)
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td style="text-align: center;" class="muted num">{{ $i + 1 }}</td>
                <td style="text-align: {{ $start }}; font-size: 8pt;" class="muted"><bdo dir="ltr" class="num">{{ $item->sku ?: '—' }}</bdo></td>
                <td style="text-align: {{ $start }};">
                    <b>{{ $item->name }}</b>
                    @if ($item->variant_label)<div class="muted" style="font-size: 8pt;">{{ $item->variant_label }}</div>@endif
                </td>
                <td style="text-align: center;" class="num">{{ $item->quantity }}</td>
                <td style="text-align: {{ $end }};" class="num">{{ $money($item->unit_price) }}</td>
                <td style="text-align: {{ $end }}; font-weight: bold;" class="num">{{ $money($item->line_total) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- Summary (start) · totals (end) --}}
<table style="margin-top: 4mm;">
    <tr>
        <td style="width: 52%; vertical-align: top; text-align: {{ $start }};">
            <table class="strip" style="width: auto;">
                <tr>
                    <td class="muted">{{ __('invoices.lines') }}</td><td class="num" style="font-weight: bold;">{{ $order->items->count() }}</td>
                    <td class="muted">{{ __('invoices.pieces') }}</td><td class="num" style="font-weight: bold;">{{ $pieces }}</td>
                </tr>
            </table>
            @if ($order->customer_note)
                <table class="card" style="margin-top: 3mm;"><tr><td class="card-head">{{ __('invoices.note') }}</td></tr><tr><td class="card-body">{{ $order->customer_note }}</td></tr></table>
            @endif
        </td>
        <td style="width: 4%;"></td>
        <td style="width: 44%; vertical-align: top;">
            <table>
                <tr><td class="muted" style="padding: 1.4mm 3mm; text-align: {{ $start }};">{{ __('invoices.subtotal') }}</td><td class="num" style="padding: 1.4mm 3mm; text-align: {{ $end }};">{{ $money($order->subtotal) }}</td></tr>
                @if ($order->discount_total > 0)
                    <tr><td class="muted" style="padding: 1.4mm 3mm; text-align: {{ $start }};">{{ __('invoices.discount') }}</td><td class="num" style="padding: 1.4mm 3mm; text-align: {{ $end }};">-{{ $money($order->discount_total) }}</td></tr>
                @endif
                @unless ($isCompany)
                    <tr><td class="muted" style="padding: 1.4mm 3mm; text-align: {{ $start }};">{{ __('invoices.shipping') }}</td><td class="num" style="padding: 1.4mm 3mm; text-align: {{ $end }};">{{ $order->shipping_total > 0 ? $money($order->shipping_total) : __('invoices.free') }}</td></tr>
                @endunless
                <tr class="total-row">
                    <td style="text-align: {{ $start }}; font-size: 10.5pt;">{{ __('invoices.total') }}</td>
                    <td class="num" style="text-align: {{ $end }}; font-size: 13pt;">{{ $money($order->total) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- B2B: receipt and company stamp --}}
@if ($isCompany)
    <table style="margin-top: 16mm;">
        <tr>
            <td style="width: 30%;" class="sign">{{ __('invoices.received_by') }}</td>
            <td style="width: 5%;"></td>
            <td style="width: 30%;" class="sign">{{ __('invoices.signature_date') }}</td>
            <td style="width: 5%;"></td>
            <td style="width: 30%;" class="sign">{{ __('invoices.company_stamp') }}</td>
        </tr>
    </table>
@endif
@endsection
