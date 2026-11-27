<?php

return [

    /*
     * The property id of which you want to display data.
     */
    'property_id' => env('ANALYTICS_PROPERTY_ID'),

    /*
     * Path to the client secret json file. Take a look at the README of this package
     * to learn how to get this file. You can also pass the credentials as an array
     * instead of a file path.
     */
    // Outside public/ and git-ignored (storage/app). Never commit this file: it is a Google service-account key.
    'service_account_credentials_json' => env('ANALYTICS_CREDENTIALS_PATH') ?: storage_path('app/analytics/service-account-credentials.json'),

    /*
     * The amount of minutes the Google API responses will be cached.
     * If you set this to zero, the responses won't be cached at all.
     */
    'cache_lifetime_in_minutes' => (int) env('ANALYTICS_CACHE_MINUTES', 60),

    /*
     * Here you may configure the "store" that the underlying Google_Client will
     * use to store it's data.  You may also add extra parameters that will
     * be passed on setCacheConfig (see docs for google-api-php-client).
     *
     * Optional parameters: "lifetime", "prefix"
     */
    'cache' => [
        'store' => env('ANALYTICS_CACHE_STORE', 'file'),
    ],

    /*
     * Ovalete: send page views from the storefront (gtag.js with the Measurement ID from Admin → Settings → SEO).
     * Off outside production by default so local testing never pollutes the real reports.
     */
    'tracking' => (bool) env('ANALYTICS_TRACKING', env('APP_ENV') === 'production'),
];
