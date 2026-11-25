@php
    $brand = '#9a4a24';
    $muted = '#7a6f69';
    $start = $dir === 'rtl' ? 'right' : 'left';
@endphp
<!DOCTYPE html>
<html lang="{{ $mailLocale }}" dir="{{ $dir }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $siteName }}</title></head>
<body style="margin:0;padding:0;background:#f5efeb;font-family:Tahoma,'Segoe UI',Arial,sans-serif;color:#2b211c;direction:{{ $dir }};">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5efeb;padding:32px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
            <tr><td align="center" style="padding:0 0 20px;">
                @if ($logo)<img src="{{ $logo }}" alt="{{ $siteName }}" height="56" style="height:56px;border:0;">@else<span style="font-size:22px;font-weight:bold;color:{{ $brand }};">{{ $siteName }}</span>@endif
            </td></tr>
            <tr><td style="background:#fff;border:1px solid #ece4df;border-radius:14px;overflow:hidden;">
                <div style="background:{{ $brand }};padding:28px 32px;text-align:{{ $start }};">
                    <h1 style="margin:0;font-size:22px;color:#fff;">{{ __('traders.mail_heading', [], $mailLocale) }}</h1>
                </div>
                <div style="padding:28px 32px;text-align:{{ $start }};font-size:15px;line-height:1.75;">
                    <p style="margin:0 0 12px;">{{ __('traders.mail_body', ['name' => $trader->name], $mailLocale) }}</p>
                    @if ($tier)
                        <p style="margin:0 0 12px;">{{ __('traders.mail_tier', [], $mailLocale) }} <strong style="color:{{ $brand }};">{{ $tier }}</strong></p>
                    @endif
                    <p style="margin:0 0 22px;color:{{ $muted }};">{{ __('traders.mail_hint', [], $mailLocale) }}</p>
                    <p style="margin:0;text-align:center;"><a href="{{ $shopUrl }}" style="display:inline-block;background:{{ $brand }};color:#fff;text-decoration:none;font-weight:bold;padding:12px 28px;border-radius:10px;">{{ __('traders.mail_cta', [], $mailLocale) }}</a></p>
                </div>
            </td></tr>
            <tr><td align="center" style="padding:18px 12px 0;font-size:12px;color:{{ $muted }};">{{ $siteName }}</td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
