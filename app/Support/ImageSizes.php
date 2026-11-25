<?php

namespace App\Support;

/** Derivative widths generated for every catalog image (see HasOptimizedImages). */
final class ImageSizes
{
    /** @var array<string, int> conversion name => max width/height in px */
    public const WIDTHS = ['thumb' => 400, 'card' => 800, 'large' => 1600];

    /**
     * Whether AVIF derivatives are registered, generated and served.
     * `media-library.avif` (env MEDIA_AVIF) forces it; otherwise it follows what the image driver can encode.
     * When off, AVIF files already on disk (e.g. generated on another machine) are simply not referenced:
     * asking Spatie for the URL of an unregistered conversion throws.
     */
    public static function avifEnabled(): bool
    {
        $forced = config('media-library.avif');
        if ($forced !== null) {
            return (bool) $forced;
        }

        static $detected = null;

        return $detected ??= config('media-library.image_driver') === 'imagick'
            ? class_exists(\Imagick::class) && \Imagick::queryFormats('AVIF') !== []
            : function_exists('imageavif') && (bool) (gd_info()['AVIF Support'] ?? false);
    }
}
