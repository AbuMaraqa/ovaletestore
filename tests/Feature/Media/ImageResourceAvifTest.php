<?php

use App\Http\Resources\ImageResource;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** A media row whose AVIF files were generated elsewhere (e.g. locally) before reaching this server. */
function mediaWithStoredAvif(): Media
{
    $product = makeSimpleProduct();
    $media = $product->addMedia(UploadedFile::fake()->image('photo.png', 900, 900))->toMediaCollection('gallery');
    $media->generated_conversions = ['thumb' => true, 'card' => true, 'card_avif' => true, 'large' => true, 'large_avif' => true];
    $media->save();

    return $media->fresh();
}

it('falls back to webp without throwing when avif is disabled but avif files were recorded', function () {
    config(['media-library.avif' => false]);

    $payload = (new ImageResource(mediaWithStoredAvif()))->resolve();

    expect($payload['avif_srcset'])->toBeNull()
        ->and($payload['srcset'])->toContain('800w')->toContain('1600w');
});

it('serves the avif srcset when avif is enabled', function () {
    config(['media-library.avif' => true]);

    $payload = (new ImageResource(mediaWithStoredAvif()))->resolve();

    expect($payload['avif_srcset'])->toContain('800w')->toContain('1600w');
});
