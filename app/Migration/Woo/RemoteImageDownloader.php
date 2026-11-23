<?php

namespace App\Migration\Woo;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Downloads the ORIGINAL source image (WordPress "-scaled"/"-WxH" copies are avoided), verifies the bytes
 * really are an image (finfo + getimagesize), caps the size and caches by URL hash so shared files are fetched once.
 */
final class RemoteImageDownloader
{
    private const MAX_BYTES = 25 * 1024 * 1024;

    public const ALLOWED_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif', 'image/gif' => 'gif'];

    /** @return array{path:string, extension:string, hash:string} */
    public function download(string $url): array
    {
        $hash = sha1($url);
        $dir = storage_path('app/private/woo-image-cache');
        File::ensureDirectoryExists($dir);

        foreach (self::ALLOWED_MIMES as $ext) {
            if (is_file($cached = "{$dir}/{$hash}.{$ext}")) {
                return ['path' => $cached, 'extension' => $ext, 'hash' => $hash];
            }
        }

        self::assertAllowedSource($url);

        $body = null;
        foreach (self::originalCandidates($url) as $candidate) {
            // No redirects: a redirect could point the fetch at an internal host (SSRF).
            $response = Http::withUserAgent('OvaleteMigrator/1.0')->withoutRedirecting()->timeout(120)->get($candidate);
            if ($response->successful()) {
                $body = $response->body();
                break;
            }
        }

        if ($body === null || $body === '') {
            throw new RuntimeException("Image download failed: {$url}");
        }
        if (strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException("Image too large: {$url}");
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body);
        $ext = self::ALLOWED_MIMES[$mime] ?? null;
        if ($ext === null || @getimagesizefromstring($body) === false) {
            throw new RuntimeException("Not a valid image ({$mime}): {$url}");
        }

        File::put($path = "{$dir}/{$hash}.{$ext}", $body);

        return ['path' => $path, 'extension' => $ext, 'hash' => $hash];
    }

    /**
     * Original first: strip WordPress "-scaled" and "-{w}x{h}" suffixes, then fall back to the given URL.
     *
     * @return list<string>
     */
    public static function originalCandidates(string $url): array
    {
        $url = str_starts_with($url, '//') ? 'https:'.$url : $url;
        $original = preg_replace('/-(scaled|\d+x\d+)(\.[a-z0-9]+)$/i', '$2', $url);

        return array_values(array_unique([self::encodeUrl($original), self::encodeUrl($url)]));
    }

    /**
     * SSRF guard: images are only fetched over HTTPS from the source store's host (WOO_BASE_URL),
     * never from arbitrary or internal addresses, even if the seed/migration data is tampered with.
     */
    public static function assertAllowedSource(string $url): void
    {
        $url = str_starts_with($url, '//') ? 'https:'.$url : $url;
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = strtolower((string) parse_url((string) config('services.woocommerce.base_url'), PHP_URL_HOST));

        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || $host === '' || $allowed === ''
            || ($host !== $allowed && $host !== 'www.'.$allowed && 'www.'.$host !== $allowed)) {
            throw new RuntimeException("Image source not allowed: {$url}");
        }
    }

    /** Source URLs contain raw Arabic file names; percent-encode path segments safely. */
    private static function encodeUrl(string $url): string
    {
        $parts = parse_url($url);
        $path = implode('/', array_map(fn ($s) => rawurlencode(rawurldecode($s)), explode('/', $parts['path'] ?? '')));

        return "{$parts['scheme']}://{$parts['host']}{$path}";
    }
}
