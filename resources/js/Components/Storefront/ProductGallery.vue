<script setup lang="ts">
import { ref, watch } from 'vue';
import ResponsiveImage from '@/Components/Common/ResponsiveImage.vue';
import type { Image } from '@/types';

const props = defineProps<{ images: Image[]; name: string }>();
const active = ref(0);
watch(() => props.images, () => (active.value = 0));
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="relative aspect-square overflow-hidden rounded-[2rem] bg-white ring-1 ring-stone-100">
            <!-- Main image is the LCP element: eager + high priority. -->
            <ResponsiveImage
                :image="images[active] ?? null"
                :alt="name"
                priority
                sizes="(min-width: 1024px) 55vw, 100vw"
                img-class="size-full object-contain p-6 sm:p-10"
            />
        </div>
        <div v-if="images.length > 1" class="flex gap-3 overflow-x-auto pb-1">
            <button
                v-for="(image, i) in images"
                :key="`${image.id}-${i}`"
                type="button"
                :class="['size-20 shrink-0 overflow-hidden rounded-2xl bg-white ring-2 transition', i === active ? 'ring-brand-500' : 'ring-transparent hover:ring-stone-200']"
                :aria-label="`${name} ${i + 1}`"
                :aria-pressed="i === active"
                @click="active = i"
            >
                <ResponsiveImage :image="image" :alt="''" sizes="80px" img-class="size-full object-contain p-1.5" />
            </button>
        </div>
    </div>
</template>
