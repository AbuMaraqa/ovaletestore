<?php

namespace App\Support\Csp;

use App\Settings\SeoSettings;
use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;
use Spatie\Csp\Value;

/**
 * Strict nonce-based CSP. Everything (fonts, images, scripts) is self-hosted; the only third party is Google Analytics,
 * allowed only while a GA4 Measurement ID is configured and tracking is on (see app.blade.php).
 * data: images = Laravolt SVG avatars; blob: = admin upload previews.
 */
class StorePolicy implements Preset
{
    public function configure(Policy $policy): void
    {
        $policy
            ->add(Directive::BASE, Keyword::SELF)
            ->add(Directive::DEFAULT, Keyword::SELF)
            ->add(Directive::CONNECT, Keyword::SELF)
            ->add(Directive::FONT, [Keyword::SELF, 'data:', 'blob:']) // blob: = font preview before upload
            ->add(Directive::FORM_ACTION, Keyword::SELF)
            ->add(Directive::FRAME_ANCESTORS, Keyword::SELF)
            // Store-location maps (Google Maps embed, no API key / no script on our pages).
            ->add(Directive::FRAME, 'https://www.google.com')
            ->add(Directive::IMG, [Keyword::SELF, 'data:', 'blob:'])
            ->add(Directive::MEDIA, Keyword::SELF)
            ->add(Directive::OBJECT, Keyword::NONE)
            ->add(Directive::SCRIPT, Keyword::SELF)
            ->add(Directive::STYLE, Keyword::SELF)
            ->addNonce(Directive::SCRIPT)
            ->addNonce(Directive::STYLE);

        if ($this->tracksAnalytics()) {
            $policy
                ->add(Directive::SCRIPT, 'https://www.googletagmanager.com')
                ->add(Directive::CONNECT, ['https://*.google-analytics.com', 'https://*.analytics.google.com', 'https://*.googletagmanager.com'])
                ->add(Directive::IMG, ['https://*.google-analytics.com', 'https://*.googletagmanager.com']);
        }

        if (app()->isProduction()) {
            $policy->add(Directive::UPGRADE_INSECURE_REQUESTS, Value::NO_VALUE);
        }
    }

    private function tracksAnalytics(): bool
    {
        try {
            return config('analytics.tracking') && filled(app(SeoSettings::class)->ga_measurement_id);
        } catch (\Throwable) {
            return false; // settings table not migrated yet (install, tests)
        }
    }
}
