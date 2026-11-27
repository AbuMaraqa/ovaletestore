<?php

namespace App\Support;

use App\Settings\GeneralSettings;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Generates the site icons (GD) into storage/app/public/branding/icons:
 *  - favicon.ico (16/32/48) + favicon-32.png — tiny sizes: a crisp brand mark (gold initial on cocoa),
 *    because a detailed logo turns into a smudge at 16–32 px; an uploaded favicon is used instead if set;
 *  - apple-touch-icon.png (180) and icon-192/512.png — the logo, trimmed of its empty margins, on a
 *    white tile (home-screen icons and the web app manifest).
 * Regenerated whenever the logo or favicon changes (SettingsController) or with `php artisan branding:icons`.
 */
final class SiteIcons
{
    public const DIR = 'branding/icons';

    private const COCOA = [43, 29, 23];

    private const GOLD = [214, 176, 98];

    public static function generate(): bool
    {
        try {
            $settings = app(GeneralSettings::class);
        } catch (Throwable) {
            return false;
        }

        $disk = Storage::disk('public');
        $disk->makeDirectory(self::DIR);
        $logo = self::load($settings->logo_path);
        $favicon = self::load($settings->favicon_path);

        // Tiny sizes: uploaded favicon, else the monogram.
        $small = [];
        foreach ([16, 32, 48] as $size) {
            $small[$size] = self::png($favicon ? self::tile($favicon, $size, false) : self::monogram($settings, $size));
        }
        $disk->put(self::DIR.'/favicon-32.png', $small[32]);
        $ico = self::ico($small);
        $disk->put(self::DIR.'/favicon.ico', $ico);
        @file_put_contents(public_path('favicon.ico'), $ico); // browsers also request /favicon.ico directly

        // Home-screen sizes: the trimmed logo on white (falls back to the monogram).
        foreach (['apple-touch-icon' => 180, 'icon-192' => 192, 'icon-512' => 512] as $name => $size) {
            $image = $logo ? self::tile($logo, $size, true) : self::monogram($settings, $size);
            $disk->put(self::DIR."/{$name}.png", self::png($image));
        }

        $disk->put(self::DIR.'/version.txt', (string) time());

        return true;
    }

    /** <link> tags for the layout (cache-busted), or [] before the icons exist. */
    public static function links(): array
    {
        $disk = Storage::disk('public');
        if (! $disk->exists(self::DIR.'/favicon.ico')) {
            return [];
        }
        $v = trim((string) $disk->get(self::DIR.'/version.txt'));
        $url = fn (string $file) => asset('storage/'.self::DIR.'/'.$file).'?v='.$v;

        return [
            ['rel' => 'icon', 'href' => $url('favicon.ico'), 'sizes' => '48x48'],
            ['rel' => 'icon', 'href' => $url('favicon-32.png'), 'type' => 'image/png', 'sizes' => '32x32'],
            ['rel' => 'apple-touch-icon', 'href' => $url('apple-touch-icon.png'), 'sizes' => '180x180'],
            ['rel' => 'manifest', 'href' => route('manifest')],
        ];
    }

    /** Web app manifest (Android home screen / install). */
    public static function manifest(string $name): array
    {
        $disk = Storage::disk('public');
        $v = $disk->exists(self::DIR.'/version.txt') ? trim((string) $disk->get(self::DIR.'/version.txt')) : '0';
        $icon = fn (int $size) => ['src' => asset('storage/'.self::DIR."/icon-{$size}.png").'?v='.$v, 'sizes' => "{$size}x{$size}", 'type' => 'image/png'];

        return [
            'name' => $name,
            'short_name' => $name,
            'start_url' => '/',
            'display' => 'standalone',
            'background_color' => '#fbf7f3',
            'theme_color' => '#2b1d17',
            'icons' => [$icon(192), $icon(512)],
        ];
    }

    private static function load(?string $path): ?GdImage
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }
        $image = @imagecreatefromstring((string) Storage::disk('public')->get($path));

        return $image instanceof GdImage ? $image : null;
    }

    /** The image trimmed of empty (white/transparent) margins, centred on a square tile. */
    private static function tile(GdImage $source, int $size, bool $whiteTile): GdImage
    {
        [$x, $y, $w, $h] = self::contentBox($source);
        $canvas = self::canvas($size, $whiteTile ? [255, 255, 255] : null);
        $pad = (int) round($size * ($whiteTile ? 0.1 : 0.04));
        $scale = min(($size - 2 * $pad) / $w, ($size - 2 * $pad) / $h);
        $dw = (int) round($w * $scale);
        $dh = (int) round($h * $scale);
        imagecopyresampled($canvas, $source, intdiv($size - $dw, 2), intdiv($size - $dh, 2), $x, $y, $dw, $dh, $w, $h);

        return $canvas;
    }

    /** Gold initial on a cocoa rounded square: readable at 16 px. */
    private static function monogram(GeneralSettings $settings, int $size): GdImage
    {
        $canvas = self::canvas($size, null);
        $cocoa = imagecolorallocate($canvas, ...self::COCOA);
        $gold = imagecolorallocate($canvas, ...self::GOLD);
        $radius = (int) round($size * 0.22);
        self::roundedRect($canvas, 0, 0, $size - 1, $size - 1, $radius, (int) $cocoa);

        $name = (string) ($settings->site_name['en'] ?? 'O');
        $letter = mb_strtoupper(mb_substr(trim($name) !== '' ? trim($name) : 'O', 0, 1));
        $font = base_path('vendor/mpdf/mpdf/ttfonts/DejaVuSerif-BoldItalic.ttf');
        if (is_file($font)) {
            $fontSize = $size * 0.56;
            $box = imagettfbbox($fontSize, 0, $font, $letter);
            $tw = $box[2] - $box[0];
            $th = $box[1] - $box[7];
            imagettftext($canvas, $fontSize, 0, (int) round(($size - $tw) / 2 - $box[0]), (int) round(($size + $th) / 2 - $box[1]), (int) $gold, $font, $letter);
        }

        return $canvas;
    }

    /** @return array{0: int, 1: int, 2: int, 3: int} x, y, width, height of the non-empty pixels */
    private static function contentBox(GdImage $img): array
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $minX = $w;
        $minY = $h;
        $maxX = -1;
        $maxY = -1;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
                $empty = $c['alpha'] > 110 || ($c['red'] > 245 && $c['green'] > 245 && $c['blue'] > 245);
                if (! $empty) {
                    $minX = min($minX, $x);
                    $maxX = max($maxX, $x);
                    $minY = min($minY, $y);
                    $maxY = max($maxY, $y);
                }
            }
        }

        return $maxX < 0 ? [0, 0, $w, $h] : [$minX, $minY, $maxX - $minX + 1, $maxY - $minY + 1];
    }

    /** @param array{0: int, 1: int, 2: int}|null $rgb background, or transparent */
    private static function canvas(int $size, ?array $rgb): GdImage
    {
        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $bg = $rgb ? imagecolorallocate($canvas, ...$rgb) : imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, (int) $bg);
        imagealphablending($canvas, true);

        return $canvas;
    }

    private static function roundedRect(GdImage $img, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $color);
        }
    }

    private static function png(GdImage $img): string
    {
        ob_start();
        imagepng($img, null, 9);

        return (string) ob_get_clean();
    }

    /** ICO container with PNG-encoded entries (supported by every current browser). @param array<int, string> $pngs */
    private static function ico(array $pngs): string
    {
        $header = pack('vvv', 0, 1, count($pngs));
        $offset = 6 + 16 * count($pngs);
        $entries = '';
        $data = '';
        foreach ($pngs as $size => $png) {
            $entries .= pack('CCCCvvVV', $size >= 256 ? 0 : $size, $size >= 256 ? 0 : $size, 0, 0, 1, 32, strlen($png), $offset);
            $data .= $png;
            $offset += strlen($png);
        }

        return $header.$entries.$data;
    }
}
