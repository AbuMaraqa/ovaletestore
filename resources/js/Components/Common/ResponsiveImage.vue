<script setup lang="ts">
import type { Image } from '@/types';

/**
 * AVIF → WebP → original fallback with srcset. Above-the-fold images pass `priority`
 * (eager + fetchpriority=high); everything else lazy-loads with async decoding.
 */
const props = withDefaults(
    defineProps<{
        image: Image | null;
        sizes?: string;
        priority?: boolean;
        alt?: string;
        imgClass?: string;
    }>(),
    { sizes: '(min-width: 1024px) 25vw, 50vw', priority: false, alt: undefined, imgClass: '' },
);
</script>

<template>
    <picture v-if="props.image" class="contents">
        <source v-if="props.image.avif_srcset" type="image/avif" :srcset="props.image.avif_srcset" :sizes="props.sizes" />
        <source v-if="props.image.srcset" type="image/webp" :srcset="props.image.srcset" :sizes="props.sizes" />
        <img
            :src="props.image.src"
            :alt="props.alt ?? props.image.alt"
            :loading="props.priority ? 'eager' : 'lazy'"
            :fetchpriority="props.priority ? 'high' : 'auto'"
            decoding="async"
            :class="props.imgClass"
        />
    </picture>
    <div v-else :class="['flex items-center justify-center bg-stone-100 text-stone-300', props.imgClass]" aria-hidden="true">
        <svg viewBox="0 0 24 24" class="size-1/3 max-h-16" fill="none" stroke="currentColor" stroke-width="1.5">
            <rect x="3" y="3" width="18" height="18" rx="3" />
            <circle cx="9" cy="9" r="2" />
            <path d="m21 15-5-5L5 21" />
        </svg>
    </div>
</template>
