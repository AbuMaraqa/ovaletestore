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

    // Arabic (default) lives at "/" so legacy WooCommerce URLs (/product/{slug}) keep working.
    'hideDefaultLocaleInURL' => true,

    'localesOrder' => ['ar', 'en', 'he'],
    'localesMapping' => [],
    'utf8suffix' => env('LARAVELLOCALIZATION_UTF8SUFFIX', '.UTF-8'),
    'urlsIgnored' => ['/up', '/sitemap*', '/robots.txt', '/media/*', '/storage/*', '/build/*'],
    'httpMethodsIgnored' => ['POST', 'PUT', 'PATCH', 'DELETE'],
];
