<?php

namespace App\Support;

use App\Settings\GeneralSettings;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * The site typeface chosen in Admin → Settings → General.
 *
 * Tajawal ships with the build. A custom font (e.g. the licensed OT Magnit Arabic) is uploaded by the owner
 * as WOFF2/WOFF/TTF/OTF files per weight, stored on the public disk under generated names, and injected as
 * font-face rules plus a --font-sans override in a nonce'd <style> (CSP-safe, same origin). Tajawal and
 * IBM Plex Sans Hebrew stay as fallbacks for any glyph the custom font lacks.
 */
final class SiteFont
{
    /** weight => form field */
    public const WEIGHTS = [400 => 'font_regular', 500 => 'font_medium', 700 => 'font_bold'];

    /** Magic bytes → extension/format. The extension is never taken from the uploaded name. */
    private const SIGNATURES = [
        'wOF2' => ['woff2', 'woff2'],
        'wOFF' => ['woff', 'woff'],
        'OTTO' => ['otf', 'opentype'],
        "\x00\x01\x00\x00" => ['ttf', 'truetype'],
        'true' => ['ttf', 'truetype'],
    ];

    private const FALLBACK = "'Tajawal', 'IBM Plex Sans Hebrew', ui-sans-serif, system-ui, sans-serif";

    /** Letters, digits, spaces and dashes only: the name is written into CSS. */
    public const NAME_PATTERN = '/^[\p{L}\p{N}][\p{L}\p{N} \-]{1,59}$/u';

    /** @return array{0: string, 1: string}|null [extension, css format] for a real font file */
    public static function detect(UploadedFile $file): ?array
    {
        $handle = @fopen((string) $file->getRealPath(), 'rb');
        $head = $handle ? (string) fread($handle, 4) : '';
        if ($handle) {
            fclose($handle);
        }

        return self::SIGNATURES[$head] ?? null;
    }

    /** CSS for the current page area, or null when the bundled Tajawal is used. */
    public static function css(bool $admin): ?string
    {
        $font = self::active($admin);
        if ($font === null) {
            return null;
        }

        $faces = '';
        foreach ($font['files'] as $weight => $path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $format = ['woff2' => 'woff2', 'woff' => 'woff', 'otf' => 'opentype', 'ttf' => 'truetype'][$ext] ?? null;
            if ($format === null) {
                continue;
            }
            // unicode-range leaves out 0-9: Arabic fonts such as OT Magnit draw them as Arabic-Indic (٠-٩); the owner
            // wants Western digits (prices, phone numbers), so digits fall through to Tajawal.
            $faces .= sprintf(
                "@font-face{font-family:'%s';src:url('%s') format('%s');font-weight:%d;font-style:normal;font-display:swap;unicode-range:U+0000-002F,U+003A-10FFFF}",
                $font['name'], asset('storage/'.$path), $format, $weight,
            );
        }

        return $faces.sprintf(':root{--font-sans:\'%s\', %s}', $font['name'], self::FALLBACK);
    }

    /** URL of the regular weight, preloaded so text does not swap late. */
    public static function preloadUrl(bool $admin): ?string
    {
        $font = self::active($admin);
        $path = $font['files'][400] ?? null;

        return $path ? asset('storage/'.$path) : null;
    }

    /**
     * The custom font for mPDF documents in a language: mPDF embeds TTF/OTF only (not WOFF/WOFF2), with
     * OpenType shaping for Arabic. The key changes with the files, so mPDF's font cache never goes stale.
     *
     * @return array{key: string, dir: string, files: array{R: string, B: string, useOTL: int, useKashida: int}}|null
     */
    public static function pdfFont(string $locale): ?array
    {
        try {
            $settings = app(GeneralSettings::class);
        } catch (Throwable) {
            return null;
        }
        if ($settings->font_family !== 'custom' || ! in_array($locale, $settings->font_locales, true)) {
            return null;
        }

        $usable = fn ($path) => is_string($path) && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['ttf', 'otf'], true)
            && is_file(storage_path('app/public/'.$path));
        $regular = $settings->font_files[400] ?? null;
        if (! $usable($regular)) {
            return null;
        }
        $bold = $settings->font_files[700] ?? null;
        $bold = $usable($bold) ? $bold : $regular;

        return [
            'key' => 'sitefont'.substr(md5($regular.'|'.$bold), 0, 8),
            'dir' => storage_path('app/public/fonts'),
            'files' => ['R' => basename((string) $regular), 'B' => basename((string) $bold), 'useOTL' => 0xFF, 'useKashida' => 75],
        ];
    }

    /** @return array{name: string, files: array<int, string>}|null */
    private static function active(bool $admin): ?array
    {
        try {
            $settings = app(GeneralSettings::class);
        } catch (Throwable) {
            return null; // settings not migrated yet
        }

        if ($settings->font_family !== 'custom' || ($admin && $settings->font_scope === 'storefront')
            || ! in_array(app()->getLocale(), $settings->font_locales, true)) {
            return null; // e.g. an Arabic typeface on the English/Hebrew pages keeps Tajawal
        }
        $name = (string) $settings->font_name;
        $files = array_filter($settings->font_files, fn ($path) => is_string($path) && $path !== '');
        if (! preg_match(self::NAME_PATTERN, $name) || ! isset($files[400])) {
            return null;
        }
        ksort($files);

        return ['name' => $name, 'files' => $files];
    }
}
