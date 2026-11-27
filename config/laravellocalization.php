<?php

return [
    // Order matters: first = default fallback for the switcher display.
    'supportedLocales' => [
        'ar' => ['name' => 'Arabic',  'script' => 'Arab', 'native' => 'العربية', 'regional' => 'ar_PS', 'dir' => 'rtl', 'og' => 'ar_AR', 'hreflang' => 'ar'],
        'en' => ['name' => 'English', 'script' => 'Latn', 'native' => 'English', 'regional' => 'en_US', 'dir' => 'ltr', 'og' => 'en_US', 'hreflang' => 'en'],
        'he' => ['name' => 'Hebrew',  'script' => 'Hebr', 'native' => 'עברית',   'regional' => 'he_IL', 'dir' => 'rtl', 'og' => 'he_IL', 'hreflang' => 'he'],
    ],

    // Crawlers must always get Arabic on "/"; visitors choose explicitly via the switcher.
    'useAcceptLanguageHeader' => false,

    // Every language is visible in the URL (/ar, /en, /he). Unprefixed URLs — including the legacy
    // WooCommerce ones (/product/{slug}) — are 301-redirected to /ar/... by RedirectToLocalePrefix.
    'hideDefaultLocaleInURL' => false,

    'localesOrder' => ['ar', 'en', 'he'],
    'localesMapping' => [],
    'utf8suffix' => env('LARAVELLOCALIZATION_UTF8SUFFIX', '.UTF-8'),
    'urlsIgnored' => ['/up', '/sitemap*', '/sitemaps/*', '/robots.txt', '/media/*', '/storage/*', '/build/*', '/favicon.ico', '/site.webmanifest'],
    'httpMethodsIgnored' => ['POST', 'PUT', 'PATCH', 'DELETE'],
];
