<?php

namespace App\Support;

/** Derivative widths generated for every catalog image (see HasOptimizedImages). */
final class ImageSizes
{
    /** @var array<string, int> conversion name => max width/height in px */
    public const WIDTHS = ['thumb' => 400, 'card' => 800, 'large' => 1600];
}
