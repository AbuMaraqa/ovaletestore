@php
    /** @var \App\Models\Order $order */
    $brand = '#9a4a24';
    $brandLight = '#fbf3ee';
    $border = '#ece4df';
    $muted = '#7a6f69';
    $text = '#2b211c';
    $start = $dir === 'rtl' ? 'right' : 'left';
    $end = $dir === 'rtl' ? 'left' : 'right';
    $font = "Tahoma, 'Segoe UI', Arial, sans-serif";
    $statusColors = ['pending' => '#b7791f', 'confirmed' => '#2b6cb0', 'processing' => '#6b46c1', 'shipped' => '#2c7a7b', 'completed' => '#2f855a', 'cancelled' => '#c53030'];
    $statusColor = $statusColors[$status->value] ?? $brand;
    $t = fn (string $key, array $replace = []) => __("mail.{$key}", $replace, $mailLocale);
@endphp
<!DOCTYPE html>
<html lang="{{ $mailLocale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $siteName }}</title>
</head>
<body style="margin:0;padding:0;background:#f5efeb;font-family:{{ $font }};color:{{ $text }};direction:{{ $dir }};">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5efeb;padding:32px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;">
                {{-- Logo / site name --}}
                <tr>
                    <td align="center" style="padding:0 0 20px;">
                        @if ($logo)
                            <img src="{{ $logo }}" alt="{{ $siteName }}" height="56" style="height:56px;max-width:220px;border:0;">
                        @else
                            <span style="font-size:22px;font-weight:bold;color:{{ $brand }};">{{ $siteName }}</span>
                        @endif
                    </td>
                </tr>

                <tr>
                    <td style="background:#ffffff;border:1px solid {{ $border }};border-radius:14px;overflow:hidden;">
                        {{-- Heading bar (WooCommerce style) --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="background:{{ $brand }};padding:30px 36px;text-align:{{ $start }};">
                                    <h1 style="margin:0;font-size:24px;line-height:1.35;color:#ffffff;font-weight:bold;">{{ $t("heading_{$kind}", ['number' => $order->number]) }}</h1>
                                </td>
                            </tr>
                        </table>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding:30px 36px 8px;text-align:{{ $start }};font-size:15px;line-height:1.7;">
                                    <p style="margin:0 0 14px;">{{ $t("intro_{$kind}", ['name' => $order->customer_name, 'number' => $order->number]) }}</p>
                                    @if ($kind === 'status')
                                        <p style="margin:0 0 6px;">
                                            {{ $t('status_now') }}
                                            <span style="display:inline-block;padding:3px 12px;border-radius:999px;background:{{ $statusColor }};color:#ffffff;font-weight:bold;font-size:13px;">{{ __("orders.status_{$status->value}", [], $mailLocale) }}</span>
                                        </p>
                                        <p style="margin:0 0 14px;color:{{ $muted }};">{{ $t("status_note_{$status->value}") }}</p>
                                    @endif
                                </td>
                            </tr>

                            {{-- Order table --}}
                            <tr>
                                <td style="padding:8px 36px 0;">
                                    <h2 style="margin:0 0 12px;font-size:17px;color:{{ $brand }};text-align:{{ $start }};">
                                        {{ $t('order_title', ['number' => $order->number]) }}
                                        <span style="font-weight:normal;color:{{ $muted }};font-size:13px;">({{ $order->placed_at->locale($mailLocale)->translatedFormat('j F Y') }})</span>
                                    </h2>
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid {{ $border }};border-collapse:collapse;font-size:14px;">
                                        <thead>
                                            <tr style="background:{{ $brandLight }};">
                                                <th style="padding:10px 12px;text-align:{{ $start }};border:1px solid {{ $border }};">{{ $t('product') }}</th>
                                                <th style="padding:10px 12px;text-align:center;border:1px solid {{ $border }};width:60px;">{{ $t('quantity') }}</th>
                                                <th style="padding:10px 12px;text-align:{{ $end }};border:1px solid {{ $border }};width:110px;">{{ $t('price') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($order->items as $item)
                                                <tr>
                                                    <td style="padding:10px 12px;text-align:{{ $start }};border:1px solid {{ $border }};vertical-align:top;">
                                                        {{ $item->name }}
                                                        @if ($item->variant_label)
                                                            <div style="font-size:12px;color:{{ $muted }};">{{ $item->variant_label }}</div>
                                                        @endif
                                                        @if ($kind === 'new_order' && $item->sku)
                                                            <div style="font-size:12px;color:{{ $muted }};" dir="ltr">SKU: {{ $item->sku }}</div>
                                                        @endif
                                                    </td>
                                                    <td style="padding:10px 12px;text-align:center;border:1px solid {{ $border }};vertical-align:top;">{{ $item->quantity }}</td>
                                                    <td style="padding:10px 12px;text-align:{{ $end }};border:1px solid {{ $border }};vertical-align:top;white-space:nowrap;" dir="ltr">{{ $money($item->line_total) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            @php
                                                $rows = [[$t('subtotal'), $money($order->subtotal)]];
                                                if ($order->discount_total > 0) { $rows[] = [$t('discount'), '-'.$money($order->discount_total)]; }
                                                $rows[] = [$t('shipping'), ($order->shipping_total > 0 ? $money($order->shipping_total) : $t('free')).($order->shipping_method_name ? ' — '.$order->shipping_method_name : '')];
                                                $rows[] = [$t('payment_method'), __("admin.pay_{$order->payment_method->value}", [], $mailLocale)];
                                            @endphp
                                            @foreach ($rows as [$label, $value])
                                                <tr>
                                                    <th colspan="2" style="padding:9px 12px;text-align:{{ $start }};border:1px solid {{ $border }};font-weight:normal;color:{{ $muted }};">{{ $label }}</th>
                                                    <td style="padding:9px 12px;text-align:{{ $end }};border:1px solid {{ $border }};">{{ $value }}</td>
                                                </tr>
                                            @endforeach
                                            <tr style="background:{{ $brandLight }};">
                                                <th colspan="2" style="padding:12px;text-align:{{ $start }};border:1px solid {{ $border }};font-size:15px;">{{ $t('total') }}</th>
                                                <td style="padding:12px;text-align:{{ $end }};border:1px solid {{ $border }};font-size:16px;font-weight:bold;color:{{ $brand }};white-space:nowrap;" dir="ltr">{{ $money($order->total) }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>

                                    @if ($order->customer_note)
                                        <p style="margin:14px 0 0;padding:12px 14px;background:{{ $brandLight }};border-radius:8px;font-size:14px;text-align:{{ $start }};">
                                            <strong>{{ $t('customer_note') }}:</strong> {{ $order->customer_note }}
                                        </p>
                                    @endif
                                </td>
                            </tr>

                            {{-- Customer / delivery --}}
                            <tr>
                                <td style="padding:24px 36px 0;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid {{ $border }};border-radius:10px;">
                                        <tr>
                                            <td style="padding:16px 18px;text-align:{{ $start }};font-size:14px;line-height:1.75;">
                                                <h3 style="margin:0 0 6px;font-size:15px;color:{{ $brand }};">{{ $t('delivery_details') }}</h3>
                                                <div style="font-weight:bold;">{{ $order->customer_name }}</div>
                                                @if ($order->shipping_city || $order->shipping_address)
                                                    <div>{{ collect([$order->shipping_city, $order->shipping_address])->filter()->implode('، ') }}</div>
                                                @endif
                                                <div dir="ltr" style="text-align:{{ $start }};">{{ $order->customer_phone }}</div>
                                                @if ($order->customer_email)
                                                    <div dir="ltr" style="text-align:{{ $start }};"><a href="mailto:{{ $order->customer_email }}" style="color:{{ $brand }};">{{ $order->customer_email }}</a></div>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            {{-- Call to action --}}
                            @php
                                $cta = $kind === 'new_order' ? [$t('cta_admin'), $adminUrl] : ($accountUrl ? [$t('cta_account'), $accountUrl] : null);
                            @endphp
                            @if ($cta)
                                <tr>
                                    <td align="center" style="padding:28px 36px 4px;">
                                        <a href="{{ $cta[1] }}" style="display:inline-block;background:{{ $brand }};color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:13px 30px;border-radius:10px;">{{ $cta[0] }}</a>
                                    </td>
                                </tr>
                            @endif

                            <tr>
                                <td style="padding:24px 36px 32px;text-align:{{ $start }};font-size:14px;line-height:1.7;color:{{ $muted }};">
                                    {{ $kind === 'new_order' ? $t('outro_new_order') : $t('outro_customer') }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td align="center" style="padding:20px 12px 0;font-size:12px;line-height:1.6;color:{{ $muted }};">
                        <strong style="color:{{ $text }};">{{ $siteName }}</strong>@if ($tagline) — {{ $tagline }}@endif<br>
                        <a href="{{ url('/') }}" style="color:{{ $brand }};text-decoration:none;">{{ parse_url(url('/'), PHP_URL_HOST) }}</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
