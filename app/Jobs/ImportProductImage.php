<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Downloads the ORIGINAL source image (never a WP thumbnail), verifies it really is an image,
 * and attaches it to the product's gallery. Idempotent: skipped if the product already has media
 * from the same source URL. Downloads are cached by URL hash so shared images are fetched once.
 */
class ImportProductImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 180];

    private const MAX_BYTES = 25 * 1024 * 1024;

    private const ALLOWED_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif', 'image/gif' => 'gif'];

    public function __construct(
        public int $productId,
        public string $sourceUrl,
        public int $position = 0,
        public ?string $alt = null,
    ) {}

    public function handle(): void
    {
        $product = Product::query()->find($this->productId);
        if (! $product) {
            return;
        }

        $hash = sha1($this->sourceUrl);

        $alreadyAttached = $product->getMedia('gallery')
            ->contains(fn ($media) => $media->getCustomProperty('source_hash') === $hash);
        if ($alreadyAttached) {
            return;
        }

        [$path, $extension] = $this->download($hash);

        $baseName = Str::slug((string) ($product->translate('en', false)?->name ?? 'product')) ?: 'product';

        $product->addMedia($path)
            ->preservingOriginal()
            ->usingName($this->alt ?: (string) $product->translate('ar', false)?->name)
            ->usingFileName(Str::limit($baseName, 60, '').'-'.substr($hash, 0, 8).'.'.$extension)
            ->withCustomProperties([
                'source_url' => $this->sourceUrl,
                'source_hash' => $hash,
                'alt' => $this->alt,
            ])
            ->setOrder($this->position + 1)
            ->toMediaCollection('gallery');
    }

    /** @return array{0:string, 1:string} local path + verified extension */
    private function download(string $hash): array
    {
        $dir = storage_path('app/private/woo-image-cache');
        File::ensureDirectoryExists($dir);

        foreach (self::ALLOWED_MIMES as $ext) {
            if (is_file($cached = "{$dir}/{$hash}.{$ext}")) {
                return [$cached, $ext];
            }
        }

        $body = null;
        foreach (self::originalCandidates($this->sourceUrl) as $url) {
            $response = Http::withUserAgent('OvaleteMigrator/1.0')->timeout(120)->get($url);
            if ($response->successful()) {
                $body = $response->body();
                break;
            }
        }

        if ($body === null || $body === '') {
            throw new RuntimeException("Image download failed: {$this->sourceUrl}");
        }

        if (strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException("Image too large: {$this->sourceUrl}");
        }

        // Trust the bytes, not the URL extension or the Content-Type header.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body);
        $ext = self::ALLOWED_MIMES[$mime] ?? null;
        if ($ext === null || @getimagesizefromstring($body) === false) {
            throw new RuntimeException("Not a valid image ({$mime}): {$this->sourceUrl}");
        }

        $path = "{$dir}/{$hash}.{$ext}";
        File::put($path, $body);

        return [$path, $ext];
    }

    /**
     * WordPress serves a downscaled "-scaled" copy for big uploads; the original sits at the same
     * URL without the suffix. Try the original first, then the given URL.
     *
     * @return list<string>
     */
    public static function originalCandidates(string $url): array
    {
        $original = preg_replace('/-scaled(\.[a-z0-9]+)$/i', '$1', $url);

        return array_values(array_unique([self::encodeUrl($original), self::encodeUrl($url)]));
    }

    /** Source URLs contain raw Arabic file names; percent-encode the path segments safely. */
    private static function encodeUrl(string $url): string
    {
        $parts = parse_url($url);
        $path = implode('/', array_map(fn ($s) => rawurlencode(rawurldecode($s)), explode('/', $parts['path'] ?? '')));

        return "{$parts['scheme']}://{$parts['host']}{$path}";
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('woo')->error("[image product #{$this->productId}] {$e->getMessage()}");
    }
}
