<?php

namespace App\Http\Resources;

use App\Support\ImageSizes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Responsive image payload for <ResponsiveImage>: AVIF + WebP srcsets with the original as a fallback
 * while queued conversions are still being generated.
 *
 * @mixin Media
 */
class ImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $webp = [];
        $avif = [];
        $avifEnabled = ImageSizes::avifEnabled();

        foreach (ImageSizes::WIDTHS as $name => $width) {
            if ($this->hasGeneratedConversion($name)) {
                $webp[] = $this->getUrl($name).' '.$width.'w';
            }
            // The conversion must also be registered here: getUrl() throws for unregistered ones.
            if ($avifEnabled && $this->hasGeneratedConversion("{$name}_avif")) {
                $avif[] = $this->getUrl("{$name}_avif").' '.$width.'w';
            }
        }

        $fallback = $this->hasGeneratedConversion('card') ? $this->getUrl('card') : $this->getUrl();

        return [
            'id' => $this->id,
            'alt' => (string) ($this->getCustomProperty('alt') ?: $this->name),
            'src' => $fallback,
            'srcset' => $webp ? implode(', ', $webp) : null,
            'avif_srcset' => $avif ? implode(', ', $avif) : null,
            'large' => $this->hasGeneratedConversion('large') ? $this->getUrl('large') : $this->getUrl(),
        ];
    }
}
