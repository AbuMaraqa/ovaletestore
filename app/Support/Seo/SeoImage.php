<?php

namespace App\Support\Seo;

use RalphJSmit\Laravel\SEO\Support\ImageMeta;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Real pixel size of a share image, so laravel-seo can print og:image:width/height and pick the right
 * Twitter card (summary_large_image for wide images, summary for square product photos).
 * The package only measures files under public/; our images live on the public disk / media library.
 */
final class SeoImage
{
    public static function meta(string $absoluteUrl, ?string $localFile): ?ImageMeta
    {
        if (! $localFile || ! is_file($localFile)) {
            return null;
        }

        $size = @getimagesize($localFile);
        if (! $size) {
            return null;
        }

        $meta = new ImageMeta($absoluteUrl); // absolute URL: the package skips its own lookup
        [$meta->width, $meta->height] = [$size[0], $size[1]];

        return $meta;
    }

    /** @return array{0: string, 1: ImageMeta|null}|null the share URL (large conversion when generated) and its size */
    public static function fromMedia(?Media $media): ?array
    {
        if (! $media) {
            return null;
        }
        $conversion = $media->hasGeneratedConversion('large') ? 'large' : '';
        $url = $media->getUrl($conversion);

        return [$url, self::meta($url, $media->getPath($conversion))];
    }
}
