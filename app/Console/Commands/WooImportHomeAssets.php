<?php

namespace App\Console\Commands;

use App\Migration\Woo\RemoteImageDownloader;
use App\Models\Category;
use App\Settings\GeneralSettings;
use App\Settings\HomeSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Throwable;

#[Signature('woo:import-home-assets')]
#[Description('Import the source homepage visuals (logo, hero, brand logos, coffee spotlight) as optimized WebP')]
class WooImportHomeAssets extends Command
{
    private const BASE = 'https://ovaletestore.com/wp-content/uploads/';

    private const HERO = '2025/07/IMG_4489.jpg';

    private const LOGO = '2025/05/cropped-ovalet-logo-2-01.png';

    private const BRANDS = ['2025/07/Untitled-1-10.jpg', '2025/07/Untitled-1-09.jpg', '2025/07/Untitled-1-08.jpg', '2025/07/Untitled-1-07-1.jpg',
        '2025/07/Untitled-1-06-1.jpg', '2025/07/Untitled-1-05-1.jpg', '2025/07/Untitled-1-04-1.jpg', '2025/07/Untitled-1-02-1.jpg', '2025/07/Untitled-1-01-1.jpg'];

    private const SPOTLIGHT = ['2025/06/WhatsApp-Image-2025-05-20-at-9.43.53-PM.jpeg', '2025/06/WhatsApp-Image-2025-05-20-at-9.43.54-PM.jpeg',
        '2025/06/WhatsApp-Image-2025-05-20-at-9.44.03-PM.jpeg', '2025/07/WhatsApp-Image-2025-07-06-at-10.14.33-PM.jpeg'];

    public function handle(RemoteImageDownloader $downloader, HomeSettings $home, GeneralSettings $general): int
    {
        $disk = Storage::disk('public');

        try {
            $hero = [];
            foreach ([960, 1920] as $width) {
                $hero[(string) $width] = $this->optimize($downloader, self::HERO, "home/hero-{$width}.webp", $width);
            }
            $home->hero_image = $hero;

            $home->brand_logos = collect(self::BRANDS)->values()
                ->map(fn ($src, $i) => $this->optimize($downloader, $src, "home/brand-{$i}.webp", 320))->all();

            $home->spotlight_images = collect(self::SPOTLIGHT)->values()
                ->map(fn ($src, $i) => $this->optimize($downloader, $src, "home/spotlight-{$i}.webp", 900))->all();

            $home->spotlight_category_id ??= Category::query()->where('source_id', 47)->value('id'); // قهوة بن اسبريسو
            $home->save();

            // Logo kept as PNG (transparency, crisp edges) at 2× display size.
            $logo = $downloader->download(self::BASE.self::LOGO);
            Image::load($logo['path'])->fit(Fit::Max, 256, 256)->save($disk->path('branding/logo.png'));
            $general->logo_path = 'branding/logo.png';
            $general->save();
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Homepage assets imported: hero (2 sizes), '.count(self::BRANDS).' brand logos, '.count(self::SPOTLIGHT).' spotlight images, logo.');

        return self::SUCCESS;
    }

    /** Downloads the original once, keeps it, writes a WebP derivative (never upscaled) and returns its public path. */
    private function optimize(RemoteImageDownloader $downloader, string $relative, string $target, int $width): string
    {
        $file = $downloader->download(self::BASE.$relative);
        $disk = Storage::disk('public');
        $disk->makeDirectory(dirname($target));

        Image::load($file['path'])->fit(Fit::Max, $width, $width * 2)->format('webp')->quality(82)->save($disk->path($target));

        return $target;
    }
}
