<script setup lang="ts">
import { ref } from 'vue';
import { useGsap } from '@/Composables/useMotion';
import { useLocale } from '@/Composables/useLocale';
import { useTrans } from '@/Composables/useTrans';

/**
 * Infinite logo marquee (GSAP). The list is rendered twice and the track moves exactly one copy's
 * width, so the loop is seamless. Direction follows reading direction; hover/focus pauses it.
 * With reduced motion it is a plain, scrollable row.
 */
const props = defineProps<{ logos: string[] }>();
const { t } = useTrans();
const { isRtl } = useLocale();
const root = ref<HTMLElement | null>(null);

useGsap(root, ({ gsap }) => {
    const tween = gsap.to('[data-marquee-track]', {
        xPercent: isRtl.value ? 50 : -50,
        duration: props.logos.length * 3.2,
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
    <section v-if="logos.length" ref="root" class="mt-24">
        <div class="container-store text-center">
            <h2 data-reveal class="text-3xl font-extrabold tracking-tight text-cocoa sm:text-4xl">{{ t('storefront.brands_title') }}</h2>
            <p data-reveal class="mx-auto mt-3 max-w-xl text-stone-500">{{ t('storefront.brands_subtitle') }}</p>
        </div>
        <div data-marquee class="relative mt-10 overflow-hidden [mask-image:linear-gradient(90deg,transparent,#000_12%,#000_88%,transparent)]">
            <div data-marquee-track class="flex w-max gap-5 will-change-transform">
                <div
                    v-for="(logo, i) in [...logos, ...logos]"
                    :key="i"
                    class="flex h-28 w-44 shrink-0 items-center justify-center rounded-3xl bg-white p-4 ring-1 ring-stone-100 sm:h-32 sm:w-52"
                    :aria-hidden="i >= logos.length"
                >
                    <img :src="logo" alt="" loading="lazy" decoding="async" class="max-h-full w-auto object-contain mix-blend-multiply transition duration-300 hover:scale-105" width="200" height="200" />
                </div>
            </div>
        </div>
    </section>
</template>
