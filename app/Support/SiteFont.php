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
            $faces .= sprintf(
                "@font-face{font-family:'%s';src:url('%s') format('%s');font-weight:%d;font-style:normal;font-display:swap}",
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
