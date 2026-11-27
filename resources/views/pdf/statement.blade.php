{{-- Company account statement (كشف حساب): on-account orders (debit) and payments (credit) with a running balance. --}}
@extends('pdf.layout')

@php
    $start = $dir === 'rtl' ? 'right' : 'left';
    $end = $dir === 'rtl' ? 'left' : 'right';
    $owes = $totals['balance'] > 0;
@endphp

@section('body')
<table>
    <tr>
        <td style="width: 58%; vertical-align: top;">
            <table class="card"><tr><td class="card-head">{{ __('invoices.customer_company') }}</td></tr><tr><td class="card-body" style="text-align: {{ $start }};">
                    <div style="font-size: 12pt; font-weight: bold;">{{ $company->name }}</div>
                    @if ($company->contact_name)<div>{{ __('invoices.contact_person') }}: {{ $company->contact_name }}</div>@endif
                    @if ($company->phone)<div><bdo dir="ltr" class="num">{{ $company->phone }}</bdo></div>@endif
                    @if ($company->city || $company->address)
                        <div class="muted">{{ collect([$company->city, $company->address])->filter()->implode('، ') }}</div>
                    @endif
                </td></tr></table>
        </td>
        <td style="width: 2%;"></td>
        <td style="width: 40%; vertical-align: top;">
            {{-- Balance due, the figure the reader looks for first. --}}
            <table style="border: 1.5px solid {{ $owes ? '#9a4a24' : '#2f6b3b' }};">
                <tr><td style="padding: 3mm 4mm 0; text-align: center;" class="label">{{ $owes ? __('invoices.balance_due') : __('invoices.balance') }}</td></tr>
                <tr><td style="padding: 1mm 4mm 3.5mm; text-align: center; font-size: 17pt; font-weight: bold; color: {{ $owes ? '#9a4a24' : '#2f6b3b' }};" class="num">{{ $money($totals['balance']) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="grid" style="margin-top: 6mm;">
    <thead>
        <tr>
            <th style="text-align: {{ $start }}; width: 13%;">{{ __('invoices.date') }}</th>
            <th style="text-align: {{ $start }}; width: 15%;">{{ __('invoices.movement') }}</th>
            <th style="text-align: {{ $start }};">{{ __('invoices.reference') }}</th>
            <th style="text-align: {{ $end }}; width: 15%;">{{ __('invoices.debit') }}</th>
            <th style="text-align: {{ $end }}; width: 15%;">{{ __('invoices.credit') }}</th>
            <th style="text-align: {{ $end }}; width: 16%;">{{ __('invoices.balance') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($entries as $i => $e)
            <tr class="{{ $i % 2 ? 'alt' : '' }}">
                <td style="text-align: {{ $start }};" class="num">{{ $e['date']->format('Y-m-d') }}</td>
                <td style="text-align: {{ $start }};">
                    {{ $e['type'] === 'order' ? __('invoices.movement_order') : __('invoices.movement_payment') }}
                    @if ($e['status'] === 'cancelled')<div class="muted" style="font-size: 7.5pt;">{{ __('invoices.cancelled') }}</div>@endif
                </td>
                <td style="text-align: {{ $start }};">
                    @if ($e['reference'])<bdo dir="ltr" class="num">{{ $e['reference'] }}</bdo>@endif
                    @if ($e['type'] === 'payment')
                        <div class="muted" style="font-size: 8pt;">{{ __("admin.payment_{$e['detail']}") }}@if ($e['note']) · {{ $e['note'] }}@endif</div>
                    @elseif ($e['detail'])
                        <div class="muted" style="font-size: 8pt;">{{ __('invoices.sales_rep') }}: {{ $e['detail'] }}</div>
                    @endif
                </td>
                <td style="text-align: {{ $end }};" class="num">{{ $e['debit'] ? $money($e['debit']) : '' }}</td>
                <td style="text-align: {{ $end }};" class="num">{{ $e['credit'] ? $money($e['credit']) : '' }}</td>
                <td style="text-align: {{ $end }}; font-weight: bold;" class="num">{{ $money($e['balance']) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align: center; padding: 8mm;" class="muted">{{ __('invoices.no_movements') }}</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td colspan="3" style="text-align: {{ $start }};">{{ __('invoices.totals') }}</td>
            <td style="text-align: {{ $end }};" class="num">{{ $money($totals['debit']) }}</td>
            <td style="text-align: {{ $end }};" class="num">{{ $money($totals['credit']) }}</td>
            <td style="text-align: {{ $end }};" class="num">{{ $money($totals['balance']) }}</td>
        </tr>
    </tfoot>
</table>

<p class="muted" style="margin-top: 4mm; font-size: 8pt;">{{ __('invoices.statement_note') }}</p>

<table style="margin-top: 14mm;">
    <tr>
        <td style="width: 40%;" class="sign">{{ __('invoices.accountant_signature') }}</td>
        <td style="width: 20%;"></td>
        <td style="width: 40%;" class="sign">{{ __('invoices.customer_signature') }}</td>
    </tr>
</table>
@endsection
