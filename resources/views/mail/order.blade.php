{{--
    Order email (new order → store, confirmation and status updates → customer).
    Email-client safe: tables + inline styles only, 600px column, bulletproof buttons, a hidden preheader,
    and a layout that stacks on phones (inline-block columns with a min width).
--}}
@php
    /** @var \App\Models\Order $order */
    $ink = '#2b211c'; $brand = '#9a4a24'; $gold = '#c9a24d'; $cream = '#f6f0ea'; $soft = '#fbf7f3';
    $border = '#ece3db'; $muted = '#7a6f69'; $green = '#2f855a'; $red = '#c53030';
    $start = $dir === 'rtl' ? 'right' : 'left';
    $end = $dir === 'rtl' ? 'left' : 'right';
    $font = "Tahoma, 'Segoe UI', Arial, sans-serif";
    $t = fn (string $key, array $replace = []) => __("mail.{$key}", $replace, $mailLocale);
    $statusLabel = __("orders.status_{$status->value}", [], $mailLocale);
    $cancelled = $status === \App\Enums\OrderStatus::Cancelled;
    $isStore = $kind === 'new_order';
    $reached = array_search($status, $flow, true);
    $preheader = $isStore
        ? $t('preheader_new_order', ['name' => $order->customer_name, 'total' => $money($order->total)])
        : ($kind === 'status' ? $t("status_note_{$status->value}") : $t('preheader_confirmation', ['number' => $order->number]));
    $cta = $isStore ? [$t('cta_admin'), $adminUrl] : ($accountUrl ? [$t('cta_account'), $accountUrl] : [$t('cta_shop'), $shopUrl]);
    $pieces = (int) $order->items->sum('quantity');
@endphp
<!DOCTYPE html>
<html lang="{{ $mailLocale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $siteName }}</title>
</head>
<body style="margin:0;padding:0;background:{{ $cream }};font-family:{{ $font }};color:{{ $ink }};direction:{{ $dir }};-webkit-text-size-adjust:100%;">
{{-- Inbox preview line (hidden in the body). --}}
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;font-size:1px;line-height:1px;">{{ $preheader }}&#8204;&nbsp;&#8204;&nbsp;&#8204;&nbsp;&#8204;&nbsp;</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $cream }};">
<tr><td align="center" style="padding:28px 10px 36px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">

    {{-- Brand header --}}
    <tr>
        <td style="background:{{ $ink }};border-radius:18px 18px 0 0;padding:26px 30px 22px;text-align:center;">
            @if ($logo)
                <span style="display:inline-block;background:#ffffff;border-radius:16px;padding:8px 12px;">
                    <img src="{{ $logo }}" alt="{{ $siteName }}" height="54" style="display:block;height:54px;max-width:200px;border:0;">
                </span>
            @else
                <span style="font-size:22px;font-weight:bold;color:#ffffff;">{{ $siteName }}</span>
            @endif
            <div style="height:3px;width:56px;background:{{ $gold }};border-radius:3px;margin:18px auto 16px;font-size:0;line-height:0;">&nbsp;</div>
            <h1 style="margin:0;font-size:24px;line-height:1.35;color:#ffffff;font-weight:bold;">{{ $t("heading_{$kind}", ['number' => $order->number]) }}</h1>
            <p style="margin:12px 0 0;">
                <span style="display:inline-block;margin:3px;padding:5px 12px;border-radius:999px;background:rgba(255,255,255,0.12);color:#f3e6d6;font-size:13px;" dir="ltr">#{{ $order->number }}</span>
                <span style="display:inline-block;margin:3px;padding:5px 12px;border-radius:999px;background:rgba(255,255,255,0.12);color:#f3e6d6;font-size:13px;">{{ $order->placed_at->locale($mailLocale)->translatedFormat('j F Y') }}</span>
            </p>
        </td>
    </tr>

    {{-- Body --}}
    <tr>
        <td style="background:#ffffff;border:1px solid {{ $border }};border-top:0;padding:28px 30px 8px;text-align:{{ $start }};">
            <p style="margin:0 0 8px;font-size:17px;font-weight:bold;">{{ $isStore ? $t('hello_store') : $t('hello', ['name' => $order->customer_name]) }}</p>
            <p style="margin:0;font-size:15px;line-height:1.75;color:#4a3f38;">{{ $t("intro_{$kind}", ['name' => $order->customer_name, 'number' => $order->number]) }}</p>

            {{-- Status: tracker for customers, banner when cancelled --}}
            @if (! $isStore)
                @if ($cancelled)
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px;">
                        <tr><td style="background:#fdf0f0;border:1px solid #f3c9c9;border-radius:12px;padding:14px 16px;font-size:14px;color:{{ $red }};text-align:{{ $start }};">
                            <strong>{{ $statusLabel }}</strong> — {{ $t('status_note_cancelled') }}
                        </td></tr>
                    </table>
                @else
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:22px;background:{{ $soft }};border:1px solid {{ $border }};border-radius:14px;">
                        <tr><td style="padding:16px 12px 14px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    @foreach ($flow as $i => $step)
                                        @php $done = $reached !== false && $i <= $reached; $current = $reached === $i; @endphp
                                        <td width="25%" align="center" style="vertical-align:top;">
                                            <div style="width:30px;height:30px;line-height:30px;margin:0 auto;border-radius:50%;font-size:14px;font-weight:bold;text-align:center;{{ $done ? "background:{$green};color:#ffffff;" : "background:#ffffff;color:#b8aca4;border:2px solid #e2d8cf;line-height:26px;" }}{{ $current ? 'box-shadow:0 0 0 4px #d7efe1;' : '' }}">{{ $done ? '✓' : $i + 1 }}</div>
                                            <div style="margin-top:7px;font-size:12px;line-height:1.35;{{ $done ? "color:{$ink};font-weight:bold;" : "color:{$muted};" }}">{{ __("orders.status_{$step->value}", [], $mailLocale) }}</div>
                                        </td>
                                    @endforeach
                                </tr>
                            </table>
                            @if ($kind === 'status' || $kind === 'confirmation')
                                <p style="margin:12px 6px 0;font-size:13px;color:{{ $muted }};text-align:center;">{{ $t("status_note_{$status->value}") }}</p>
                            @endif
                        </td></tr>
                    </table>
                @endif
            @endif

            @if ($invoiceAttached)
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:16px;">
                    <tr><td style="background:#f1f7f3;border:1px solid #cfe6d8;border-radius:12px;padding:12px 16px;font-size:14px;color:#245c3f;text-align:{{ $start }};">
                        📎 {{ $t('invoice_attached') }}
                    </td></tr>
                </table>
            @endif

            {{-- Items --}}
            <h2 style="margin:28px 0 10px;font-size:16px;color:{{ $brand }};">{{ $t('items_title', ['count' => $pieces]) }}</h2>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid {{ $border }};">
                @foreach ($order->items as $item)
                    <tr>
                        <td width="64" style="padding:12px 0;border-bottom:1px solid {{ $border }};vertical-align:middle;">
                            @if ($images[$item->id] ?? null)
                                <img src="{{ $images[$item->id] }}" alt="" width="56" height="56" style="display:block;width:56px;height:56px;border-radius:10px;background:{{ $soft }};border:1px solid {{ $border }};object-fit:contain;">
                            @else
                                <div style="width:56px;height:56px;border-radius:10px;background:{{ $soft }};border:1px solid {{ $border }};"></div>
                            @endif
                        </td>
                        <td style="padding:12px 12px;border-bottom:1px solid {{ $border }};vertical-align:middle;text-align:{{ $start }};font-size:14px;line-height:1.5;">
                            <strong>{{ $item->name }}</strong>
                            @if ($item->variant_label)<div style="font-size:12px;color:{{ $muted }};">{{ $item->variant_label }}</div>@endif
                            <div style="font-size:12px;color:{{ $muted }};">
                                <span dir="ltr">{{ $item->quantity }} × {{ $money($item->unit_price) }}</span>
                                @if ($isStore && $item->sku) · <span dir="ltr">SKU {{ $item->sku }}</span>@endif
                            </div>
                        </td>
                        <td style="padding:12px 0;border-bottom:1px solid {{ $border }};vertical-align:middle;text-align:{{ $end }};font-size:14px;font-weight:bold;white-space:nowrap;" dir="ltr">{{ $money($item->line_total) }}</td>
                    </tr>
                @endforeach
            </table>

            {{-- Totals --}}
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:14px;">
                <tr>
                    <td style="padding:5px 0;font-size:14px;color:{{ $muted }};text-align:{{ $start }};">{{ $t('subtotal') }}</td>
                    <td style="padding:5px 0;font-size:14px;text-align:{{ $end }};" dir="ltr">{{ $money($order->subtotal) }}</td>
                </tr>
                @if ($order->discount_total > 0)
                    <tr>
                        <td style="padding:5px 0;font-size:14px;color:{{ $muted }};text-align:{{ $start }};">{{ $t('discount') }}</td>
                        <td style="padding:5px 0;font-size:14px;text-align:{{ $end }};color:{{ $green }};" dir="ltr">-{{ $money($order->discount_total) }}</td>
                    </tr>
                @endif
                @if (! $order->company_id)
                    <tr>
                        <td style="padding:5px 0;font-size:14px;color:{{ $muted }};text-align:{{ $start }};">{{ $t('shipping') }}@if ($order->shipping_method_name) <span style="font-size:12px;">({{ $order->shipping_method_name }})</span>@endif</td>
                        <td style="padding:5px 0;font-size:14px;text-align:{{ $end }};" dir="ltr">{{ $order->shipping_total > 0 ? $money($order->shipping_total) : $t('free') }}</td>
                    </tr>
                @endif
                <tr>
                    <td colspan="2" style="padding:8px 0 0;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $ink }};border-radius:12px;">
                            <tr>
                                <td style="padding:14px 16px;font-size:15px;font-weight:bold;color:#ffffff;text-align:{{ $start }};">{{ $t('total') }}</td>
                                <td style="padding:14px 16px;font-size:19px;font-weight:bold;color:#ffffff;text-align:{{ $end }};white-space:nowrap;" dir="ltr">{{ $money($order->total) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            @if ($order->customer_note)
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:14px;">
                    <tr><td style="background:{{ $soft }};border-radius:10px;padding:12px 14px;font-size:14px;line-height:1.6;text-align:{{ $start }};">
                        <strong>{{ $t('customer_note') }}:</strong> {{ $order->customer_note }}
                    </td></tr>
                </table>
            @endif

            {{-- Delivery · payment (two columns, stacking on phones) --}}
            <div style="margin-top:22px;font-size:0;text-align:{{ $start }};">
                <div style="display:inline-block;width:100%;max-width:258px;min-width:220px;vertical-align:top;margin:0 0 12px;font-size:14px;" dir="{{ $dir }}">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid {{ $border }};border-radius:12px;">
                        <tr><td style="padding:14px 16px;text-align:{{ $start }};line-height:1.7;">
                            <div style="font-size:12px;font-weight:bold;color:{{ $brand }};margin-bottom:4px;">{{ $t('delivery_details') }}</div>
                            <div style="font-weight:bold;">{{ $order->customer_name }}</div>
                            @if ($order->shipping_city || $order->shipping_address)
                                <div style="color:#4a3f38;">{{ collect([$order->shipping_city, $order->shipping_address])->filter()->implode('، ') }}</div>
                            @endif
                            @if ($order->customer_phone)<div dir="ltr" style="text-align:{{ $start }};">{{ $order->customer_phone }}</div>@endif
                            @if ($order->customer_email)
                                <div dir="ltr" style="text-align:{{ $start }};"><a href="mailto:{{ $order->customer_email }}" style="color:{{ $brand }};text-decoration:none;">{{ $order->customer_email }}</a></div>
                            @endif
                        </td></tr>
                    </table>
                </div>
                <div style="display:inline-block;width:10px;font-size:0;">&nbsp;</div>
                <div style="display:inline-block;width:100%;max-width:258px;min-width:220px;vertical-align:top;margin:0 0 12px;font-size:14px;" dir="{{ $dir }}">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid {{ $border }};border-radius:12px;">
                        <tr><td style="padding:14px 16px;text-align:{{ $start }};line-height:1.7;">
                            <div style="font-size:12px;font-weight:bold;color:{{ $brand }};margin-bottom:4px;">{{ $t('payment_method') }}</div>
                            <div style="font-weight:bold;">{{ __("admin.pay_{$order->payment_method->value}", [], $mailLocale) }}</div>
                            <div style="color:{{ $muted }};font-size:13px;">{{ $order->payment_method->value === 'cod' ? $t('cod_note', ['total' => $money($order->total)]) : $t('on_account_note') }}</div>
                        </td></tr>
                    </table>
                </div>
            </div>

            {{-- Main action --}}
            <table role="presentation" cellpadding="0" cellspacing="0" align="center" style="margin:18px auto 6px;">
                <tr><td style="border-radius:12px;background:{{ $brand }};">
                    <a href="{{ $cta[1] }}" style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:12px;">{{ $cta[0] }}</a>
                </td></tr>
            </table>

            {{-- Help (customers) --}}
            @if (! $isStore && ($whatsapp || $phone))
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:22px;border-top:1px solid {{ $border }};">
                    <tr><td style="padding:18px 0 4px;text-align:center;font-size:14px;color:#4a3f38;">
                        {{ $t('need_help') }}
                        <div style="margin-top:12px;">
                            @if ($whatsapp)
                                <a href="https://wa.me/{{ $whatsapp }}" style="display:inline-block;margin:4px;padding:10px 18px;border-radius:999px;background:#25D366;color:#ffffff;font-weight:bold;font-size:14px;text-decoration:none;">WhatsApp</a>
                            @endif
                            @if ($phone)
                                <a href="tel:{{ $phone }}" style="display:inline-block;margin:4px;padding:10px 18px;border-radius:999px;border:1px solid {{ $border }};color:{{ $ink }};font-weight:bold;font-size:14px;text-decoration:none;" dir="ltr">{{ $phone }}</a>
                            @endif
                        </div>
                    </td></tr>
                </table>
            @endif

            <p style="margin:22px 0 24px;font-size:13px;line-height:1.7;color:{{ $muted }};text-align:center;">{{ $isStore ? $t('outro_new_order') : $t('outro_customer') }}</p>
        </td>
    </tr>

    {{-- Footer --}}
    <tr>
        <td style="background:{{ $soft }};border:1px solid {{ $border }};border-top:0;border-radius:0 0 18px 18px;padding:18px 24px;text-align:center;font-size:12px;line-height:1.7;color:{{ $muted }};">
            <strong style="color:{{ $ink }};">{{ $siteName }}</strong>@if ($tagline) — {{ $tagline }}@endif<br>
            <a href="{{ $shopUrl }}" style="color:{{ $brand }};text-decoration:none;">{{ parse_url($shopUrl, PHP_URL_HOST) }}</a>
            @if ($contactEmail) · <a href="mailto:{{ $contactEmail }}" style="color:{{ $brand }};text-decoration:none;">{{ $contactEmail }}</a>@endif
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>
