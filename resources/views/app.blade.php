@php
    $locale = app()->getLocale();
    $dir = LaravelLocalization::getCurrentLocaleDirection();
    $isAdmin = str_starts_with($page['component'], 'Admin/') || str_starts_with($page['component'], 'Auth/');
    $seo = $page['props']['seo'] ?? null;
    // GA4 (spatie/laravel-analytics reads the same property): storefront pages only, never admin / POS / auth.
    $gaId = str_starts_with($page['component'], 'Storefront/') && config('analytics.tracking')
        ? app(\App\Settings\SeoSettings::class)->ga_measurement_id : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}" @class(['h-full'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2b1d17">
    <meta name="csp-nonce" content="{{ Vite::cspNonce() }}">

    @if (is_array($seo))
        {{-- ralphjsmit/laravel-seo tags (App\Support\Seo\SeoData), server-rendered so crawlers need no JS.
             Each has a data-inertia key; Inertia's serverHead ('seo' prop) swaps them on client navigation. --}}
        @foreach ($seo as $tag)
            {!! $tag !!}
        @endforeach
    @else
        <title data-inertia="title">{{ config('app.name') }}</title>
        <meta name="robots" content="noindex,nofollow">
    @endif

    {{-- Generated icons (App\Support\SiteIcons): tab favicon, Apple/Android home screen, web manifest. --}}
    @foreach (\App\Support\SiteIcons::links() as $icon)
        <link @foreach ($icon as $attr => $value) {{ $attr }}="{{ $value }}" @endforeach>
    @endforeach

    @routes($isAdmin ? 'admin' : 'storefront', Vite::cspNonce())
    @vite(['resources/js/app.ts', $isAdmin ? 'resources/css/admin.css' : 'resources/css/storefront.css'])

    @if ($gaId)
        {{-- Page views are sent by resources/js/analytics.ts on every Inertia visit (send_page_view: false here). --}}
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}" nonce="{{ Vite::cspNonce() }}"></script>
        <script nonce="{{ Vite::cspNonce() }}">
            window.dataLayer = window.dataLayer || [];
            window.gtag = function () { window.dataLayer.push(arguments); };
            window.gtag('js', new Date());
            window.gtag('config', @json($gaId), { send_page_view: false });
        </script>
    @endif

    {{-- Custom site font (Admin → Settings → General); after the bundle so its --font-sans wins. --}}
    @if ($fontCss = \App\Support\SiteFont::css($isAdmin))
        <link rel="preload" href="{{ \App\Support\SiteFont::preloadUrl($isAdmin) }}" as="font" crossorigin>
        <style nonce="{{ Vite::cspNonce() }}">{!! $fontCss !!}</style>
    @endif
</head>
<body @class(['h-full antialiased', 'demo1 kt-sidebar-fixed kt-header-fixed text-base text-foreground bg-background' => $isAdmin])>
    @inertia
</body>
</html>
