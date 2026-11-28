<?php

namespace App\Actions\Home;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/**
 * Homepage hero photo uploaded from Settings → Homepage: keeps the original (home/originals) and writes the two
 * WebP widths the hero's srcset uses (960 for phones, 1920 for desktop; never upscaled). Files get fresh random
 * names, so browsers and CDNs never show a cached old photo; the previous set is deleted.
 */
final class StoreHeroImage
{
    public const WIDTHS = [960, 1920];

    /**
     * @param  array<int|string, string>  $current  width => public-disk path (HomeSettings::$hero_image)
     * @return array<int, string>
     */
    public function handle(UploadedFile $file, array $current): array
    {
        $disk = Storage::disk('public');
        $name = 'hero-'.Str::lower(Str::random(10));
        $disk->makeDirectory('home/originals');
        $original = (string) $file->storeAs('home/originals', $name.'.'.$file->extension(), 'public');

        $paths = [];
        foreach (self::WIDTHS as $width) {
            $path = "home/{$name}-{$width}.webp";
            Image::load($disk->path($original))->fit(Fit::Max, $width, $width * 2)->format('webp')->quality(82)->save($disk->path($path));
            $paths[$width] = $path;
        }

        // The previous WebP sizes go (uploaded originals stay under home/originals).
        foreach ($current as $old) {
            if (! in_array($old, $paths, true)) {
                $disk->delete($old);
            }
        }

        return $paths;
    }
}
