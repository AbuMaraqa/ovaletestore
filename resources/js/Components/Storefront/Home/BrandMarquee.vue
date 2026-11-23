<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useGsap } from '@/Composables/useMotion';
import { useLocale } from '@/Composables/useLocale';
import { useTrans } from '@/Composables/useTrans';

/**
 * Infinite logo marquee (GSAP). The list is rendered twice and the track moves exactly one copy's
 * width, so the loop is seamless. Direction follows reading direction; hover/focus pauses it.
 * With reduced motion it is a plain, scrollable row.
 */
const props = defineProps<{ brands: { id: number; name: string; url: string; logo: string | null }[] }>();
const { t } = useTrans();
const { isRtl } = useLocale();
const root = ref<HTMLElement | null>(null);

useGsap(root, ({ gsap }) => {
    const tween = gsap.to('[data-marquee-track]', {
        xPercent: isRtl.value ? 50 : -50,
        duration: props.brands.length * 3.2,
        ease: 'none',
        repeat: -1,
    });

    const track = root.value!.querySelector('[data-marquee]')!;
    track.addEventListener('mouseenter', () => tween.pause());
    track.addEventListener('mouseleave', () => tween.resume());
    track.addEventListener('focusin', () => tween.pause());
    track.addEventListener('focusout', () => tween.resume());
});
</script>

<template>
    <section v-if="brands.length" ref="root" class="mt-24">
        <div class="container-store text-center">
            <h2 data-reveal class="text-3xl font-extrabold tracking-tight text-cocoa sm:text-4xl">{{ t('storefront.brands_title') }}</h2>
            <p data-reveal class="mx-auto mt-3 max-w-xl text-stone-500">{{ t('storefront.brands_subtitle') }}</p>
        </div>
        <div data-marquee class="relative mt-10 overflow-hidden [mask-image:linear-gradient(90deg,transparent,#000_12%,#000_88%,transparent)]">
            <div data-marquee-track class="flex w-max gap-5 will-change-transform">
                <Link
                    v-for="(brand, i) in [...brands, ...brands]"
                    :key="i"
                    :href="brand.url"
                    class="flex h-28 w-44 shrink-0 items-center justify-center rounded-3xl bg-white p-4 ring-1 ring-stone-100 transition hover:ring-brand-300 sm:h-32 sm:w-52"
                    :aria-hidden="i >= brands.length"
                    :tabindex="i >= brands.length ? -1 : undefined"
                    :aria-label="brand.name"
                >
                    <img v-if="brand.logo" :src="brand.logo" :alt="brand.name" loading="lazy" decoding="async" class="max-h-full w-auto object-contain mix-blend-multiply transition duration-300 hover:scale-105" width="200" height="200" />
                    <span v-else class="font-bold text-cocoa">{{ brand.name }}</span>
                </Link>
            </div>
        </div>
        <div class="mt-6 text-center">
            <Link :href="route('storefront.brands')" class="text-sm font-semibold text-brand-700 hover:underline">{{ t('storefront.view_all') }}</Link>
        </div>
    </section>
</template>
