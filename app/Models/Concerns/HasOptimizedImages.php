<?php

namespace App\Models\Concerns;

use App\Support\ImageSizes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Shared, quality-first image pipeline. The uploaded original is never modified;
 * derivatives are generated (queued) at fixed widths and never upscaled (Fit::Max).
 *   thumb 400w webp · card 800w webp+avif · large 1600w webp+avif
 * The frontend builds <picture> + srcset from these (see App\Http\Resources\ImageResource).
 */
trait HasOptimizedImages
{
    use InteractsWithMedia;

    public function registerMediaConversions(?Media $media = null): void
    {
        $collections = $this->optimizedImageCollections();

        foreach (ImageSizes::WIDTHS as $name => $width) {
            $this->addMediaConversion($name)
                ->fit(Fit::Max, $width, $width)
                ->format('webp')
                ->quality($width <= 400 ? 80 : 82)
                ->performOnCollections(...$collections);

            if ($width >= 800 && self::canEncodeAvif()) {
                $this->addMediaConversion("{$name}_avif")
                    ->fit(Fit::Max, $width, $width)
                    ->format('avif')
                    ->quality(60)
                    ->performOnCollections(...$collections);
            }
        }
    }

    /** @return list<string> */
    abstract protected function optimizedImageCollections(): array;

    /**
     * AVIF derivatives are optional: a server whose GD/Imagick cannot encode AVIF would
     * otherwise fail the whole conversion job (and skip the later webp sizes).
     * ImageResource only emits an AVIF srcset for conversions that were generated.
     */
    public static function canEncodeAvif(): bool
    {
        static $supported = null;

        return $supported ??= config('media-library.image_driver') === 'imagick'
            ? class_exists(\Imagick::class) && \Imagick::queryFormats('AVIF') !== []
            : function_exists('imageavif') && (bool) (gd_info()['AVIF Support'] ?? false);
    }

    /** @return list<string> */
    public static function acceptedImageMimes(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];
    }
}
