<?php

namespace App\Jobs;

use App\Migration\Woo\RemoteImageDownloader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

/**
 * Attaches a remote source image to any media-library model/collection (products, categories…).
 * Idempotent: skipped when the model already holds media from the same source URL.
 */
class ImportRemoteImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 180];

    /** @param class-string<Model&HasMedia> $modelClass */
    public function __construct(
        public string $modelClass,
        public int $modelId,
        public string $sourceUrl,
        public string $collection = 'gallery',
        public int $position = 0,
        public ?string $alt = null,
    ) {}

    public function handle(RemoteImageDownloader $downloader): void
    {
        /** @var (Model&HasMedia)|null $model */
        $model = $this->modelClass::query()->find($this->modelId);
        if (! $model) {
            return;
        }

        $hash = sha1($this->sourceUrl);
        if ($model->getMedia($this->collection)->contains(fn ($m) => $m->getCustomProperty('source_hash') === $hash)) {
            return;
        }

        $file = $downloader->download($this->sourceUrl);
        $englishName = method_exists($model, 'translate') ? $model->translate('en', false)?->name : null;
        $arabicName = method_exists($model, 'translate') ? $model->translate('ar', false)?->name : null;
        $baseName = Str::slug((string) ($englishName ?? class_basename($model))) ?: 'image';

        $model->addMedia($file['path'])
            ->preservingOriginal()
            ->usingName($this->alt ?: (string) $arabicName)
            ->usingFileName(Str::limit($baseName, 60, '').'-'.substr($hash, 0, 8).'.'.$file['extension'])
            ->withCustomProperties(['source_url' => $this->sourceUrl, 'source_hash' => $hash, 'alt' => $this->alt])
            ->setOrder($this->position + 1)
            ->toMediaCollection($this->collection);
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('woo')->error('[image '.class_basename($this->modelClass)." #{$this->modelId}] {$e->getMessage()}");
    }
}
