@php
    $locale = app()->getLocale();
    $dir = LaravelLocalization::getCurrentLocaleDirection();
    $isAdmin = str_starts_with($page['component'], 'Admin/') || str_starts_with($page['component'], 'Auth/');
    $seo = $page['props']['seo'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}" @class(['h-full'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2b1d17">
    <meta name="csp-nonce" content="{{ Vite::cspNonce() }}">

    @if ($seo)
        {{-- Server-rendered so crawlers get full metadata without JS; <SeoHead> replaces these on client navigation. --}}
        <title inertia>{{ $seo['title'] }}</title>
        @if ($seo['description'])
            <meta inertia="description" name="description" content="{{ $seo['description'] }}">
        @endif
        <meta inertia="robots" name="robots" content="{{ $seo['robots'] }}">
        <link inertia="canonical" rel="canonical" href="{{ $seo['canonical'] }}">
        @foreach ($seo['alternates'] as $alt)
            <link inertia="alternate-{{ $alt['hreflang'] }}" rel="alternate" hreflang="{{ $alt['hreflang'] }}" href="{{ $alt['href'] }}">
        @endforeach
        <meta inertia="og:type" property="og:type" content="{{ $seo['og']['type'] }}">
        <meta inertia="og:title" property="og:title" content="{{ $seo['title'] }}">
        @if ($seo['description'])
            <meta inertia="og:description" property="og:description" content="{{ $seo['description'] }}">
        @endif
        <meta inertia="og:url" property="og:url" content="{{ $seo['canonical'] }}">
        <meta inertia="og:site_name" property="og:site_name" content="{{ $seo['og']['site_name'] }}">
        <meta inertia="og:locale" property="og:locale" content="{{ $seo['og']['locale'] }}">
        @if ($seo['og']['image'])
            <meta inertia="og:image" property="og:image" content="{{ $seo['og']['image'] }}">
            <meta inertia="twitter:image" name="twitter:image" content="{{ $seo['og']['image'] }}">
        @endif
        <meta inertia="twitter:card" name="twitter:card" content="summary_large_image">
        @foreach ($seo['json_ld'] as $i => $schema)
            <script inertia="ld-{{ $i }}" type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
        @endforeach
    @else
        <title inertia>{{ config('app.name') }}</title>
        <meta name="robots" content="noindex,nofollow">
    @endif

    @if ($favicon = app(\App\Settings\GeneralSettings::class)->favicon_path)
        <link rel="icon" href="{{ asset('storage/'.$favicon) }}">
    @endif

    @routes($isAdmin ? 'admin' : 'storefront', Vite::cspNonce())
    @vite(['resources/js/app.ts', $isAdmin ? 'resources/css/admin.css' : 'resources/css/storefront.css'])

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
