<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import Icon from '@/Components/Storefront/Icon.vue';
import SearchForm from '@/Components/Storefront/SearchForm.vue';
import { useGsap } from '@/Composables/useMotion';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps<{ title: string; subtitle: string; image: { src: string; srcset: string } | null }>();
const { t } = useTrans();
const root = ref<HTMLElement | null>(null);

// Words are animated individually (Arabic stays joined inside each word).
const words = props.title.split(/\s+/);

useGsap(root, ({ gsap }) => {
    gsap.timeline({ defaults: { ease: 'power3.out' } })
        .from('[data-hero-bg]', { scale: 1.12, duration: 1.8, ease: 'power2.out' })
        .from('[data-hero-eyebrow]', { autoAlpha: 0, y: 16, duration: 0.6 }, 0.25)
        .from('[data-hero-word]', { autoAlpha: 0, yPercent: 60, rotate: 2, duration: 0.9, stagger: 0.06 }, 0.35)
        .from('[data-hero-sub]', { autoAlpha: 0, y: 20, duration: 0.7 }, '-=0.5')
        .from('[data-hero-cta] > *', { autoAlpha: 0, y: 16, duration: 0.6, stagger: 0.08 }, '-=0.4');

    // Parallax: background drifts slower than the page, content fades as it leaves.
    gsap.to('[data-hero-bg]', { yPercent: 18, ease: 'none', scrollTrigger: { trigger: root.value, start: 'top top', end: 'bottom top', scrub: true } });
    gsap.to('[data-hero-content]', { yPercent: -12, autoAlpha: 0.2, ease: 'none', scrollTrigger: { trigger: root.value, start: 'top top', end: 'bottom top', scrub: true } });
});
</script>

<template>
    <section ref="root" class="relative isolate flex min-h-[64svh] items-end overflow-hidden bg-cocoa lg:min-h-[86svh]">
        <img
            v-if="image"
            data-hero-bg
            :src="image.src"
            :srcset="image.srcset"
            sizes="100vw"
            alt=""
            fetchpriority="high"
            decoding="async"
            class="absolute inset-0 -z-10 size-full object-cover will-change-transform"
        />
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-cocoa via-cocoa/60 to-cocoa/10" aria-hidden="true" />
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-cocoa/70 via-transparent to-transparent rtl:bg-gradient-to-l" aria-hidden="true" />

        <div data-hero-content class="container-store pb-14 pt-32 lg:pb-24">
            <div class="max-w-3xl text-white">
                <p data-hero-eyebrow class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-medium ring-1 ring-white/20 backdrop-blur">
                    <span class="size-2 rounded-full bg-brand-400" />
                    {{ t('storefront.hero_eyebrow') }}
                </p>
                <h1 class="mt-6 text-4xl font-extrabold leading-[1.4] text-balance sm:text-5xl lg:text-7xl">
                    <span v-for="(word, i) in words" :key="i" class="inline-block overflow-hidden pt-1 pb-2 align-bottom">
                        <span data-hero-word class="inline-block">{{ word }}</span>&nbsp;
                    </span>
                </h1>
                <p data-hero-sub class="mt-5 max-w-2xl text-lg leading-relaxed text-white/80 lg:text-xl">{{ subtitle }}</p>

                <div data-hero-cta class="mt-9 flex flex-wrap items-center gap-3">
                    <Link :href="route('storefront.shop')" class="btn h-13 bg-brand-600 px-8 text-base text-white hover:bg-brand-700">
                        {{ t('storefront.hero_cta') }}
                        <Icon name="arrow" class="size-5 rtl:-scale-x-100" />
                    </Link>
                    <a href="#categories" class="btn h-13 bg-white/10 px-8 text-base text-white ring-1 ring-white/30 backdrop-blur hover:bg-white/20">
                        {{ t('storefront.hero_secondary') }}
                    </a>
                </div>

                <div class="mt-8 max-w-xl [&_input]:h-13 [&_input]:bg-white/95">
                    <SearchForm />
                </div>
            </div>
        </div>
    </section>
</template>
