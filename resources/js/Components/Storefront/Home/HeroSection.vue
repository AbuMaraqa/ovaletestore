<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import Icon from '@/Components/Storefront/Icon.vue';
import SearchForm from '@/Components/Storefront/SearchForm.vue';
import { useGsap } from '@/Composables/useMotion';
import { useTrans } from '@/Composables/useTrans';

/**
 * Editorial split hero: words on the cream page, the photograph running to the page edge with a single rounded
 * corner. No badges or effects — type, one photo and two actions. The small line under the search uses real
 * numbers (brands, categories) from the catalogue.
 */
const props = withDefaults(
    defineProps<{ title: string; subtitle: string; image: { src: string; srcset: string } | null; brandsCount?: number; categoriesCount?: number }>(),
    { brandsCount: 0, categoriesCount: 0 },
);
const { t } = useTrans();
const root = ref<HTMLElement | null>(null);

// Words are animated individually (Arabic stays joined inside each word).
const words = props.title.split(/\s+/);

useGsap(root, ({ gsap }) => {
    gsap.timeline({ defaults: { ease: 'power3.out' } })
        .from('[data-hero-photo]', { clipPath: 'inset(0 0 100% 0)', duration: 1.2, ease: 'power3.inOut' })
        .from('[data-hero-bg]', { scale: 1.12, duration: 1.8, ease: 'power2.out' }, 0)
        .from('[data-hero-word]', { autoAlpha: 0, yPercent: 60, duration: 0.85, stagger: 0.05 }, 0.25)
        .from('[data-hero-fade]', { autoAlpha: 0, y: 16, duration: 0.7, stagger: 0.08 }, '-=0.55');

    gsap.to('[data-hero-bg]', { yPercent: 10, ease: 'none', scrollTrigger: { trigger: root.value, start: 'top top', end: 'bottom top', scrub: true } });
});
</script>

<template>
    <section ref="root" class="relative lg:grid lg:min-h-[min(78svh,52rem)] lg:grid-cols-2">
        <!-- Photo: on top for phones, the end half (to the page edge) on desktop. -->
        <div data-hero-photo class="relative aspect-[4/3] overflow-hidden bg-cocoa max-lg:rounded-b-[2rem] sm:aspect-[16/9] lg:order-last lg:aspect-auto lg:rounded-es-[4rem]">
            <img
                v-if="image"
                data-hero-bg
                :src="image.src"
                :srcset="image.srcset"
                sizes="(min-width: 1024px) 50vw, 100vw"
                alt=""
                fetchpriority="high"
                decoding="async"
                class="absolute inset-0 size-full object-cover will-change-transform"
            />
        </div>

        <!-- Words: aligned with the page container's start edge. -->
        <div class="flex items-center px-4 pt-10 pb-4 sm:px-6 lg:px-8 lg:py-16 lg:ps-[max(2rem,calc((100vw-90rem)/2+2rem))] lg:pe-16 xl:pe-20">
            <div class="max-w-xl">
                <p data-hero-fade class="text-sm font-semibold text-brand-700 sm:text-base">{{ t('storefront.hero_eyebrow') }}</p>
                <h1 class="mt-4 text-[2.5rem] leading-[1.3] font-extrabold text-balance text-cocoa sm:text-5xl xl:text-6xl">
                    <span v-for="(word, i) in words" :key="i" class="inline-block overflow-hidden pt-1 pb-2 align-bottom">
                        <span data-hero-word class="inline-block">{{ word }}</span>&nbsp;
                    </span>
                </h1>
                <p data-hero-fade class="mt-4 text-lg leading-relaxed text-stone-600">{{ subtitle }}</p>

                <div data-hero-fade class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3">
                    <Link :href="route('storefront.shop')" class="btn-primary h-13 px-8 text-base">
                        {{ t('storefront.hero_cta') }}
                        <Icon name="arrow" class="size-5 rtl:-scale-x-100" />
                    </Link>
                    <a href="#categories" class="text-base font-semibold text-cocoa underline decoration-brand-300 decoration-2 underline-offset-8 transition hover:decoration-brand-600">
                        {{ t('storefront.hero_secondary') }}
                    </a>
                </div>

                <div data-hero-fade class="mt-10 [&_input]:h-13 [&_input]:ring-hairline">
                    <SearchForm />
                </div>
                <p v-if="brandsCount || categoriesCount" data-hero-fade class="mt-4 flex flex-wrap items-center gap-x-3 text-sm text-stone-500">
                    <span v-if="brandsCount">{{ t('storefront.hero_stat_brands', { count: brandsCount }) }}</span>
                    <span v-if="brandsCount && categoriesCount" class="size-1 rounded-full bg-stone-300" aria-hidden="true" />
                    <span v-if="categoriesCount">{{ t('storefront.hero_stat_categories', { count: categoriesCount }) }}</span>
                </p>
            </div>
        </div>
    </section>
</template>
