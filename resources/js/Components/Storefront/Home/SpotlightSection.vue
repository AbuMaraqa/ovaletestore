<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import Icon from '@/Components/Storefront/Icon.vue';
import ProductCard from '@/Components/Storefront/ProductCard.vue';
import { useGsap } from '@/Composables/useMotion';
import { useTrans } from '@/Composables/useTrans';
import type { ProductCard as ProductCardType } from '@/types';

/** "World-class coffee" spotlight: collage with layered parallax + the category's latest products. */
defineProps<{ spotlight: { title: string; text: string | null; url: string; images: string[]; products: ProductCardType[] } }>();
const { t } = useTrans();
const root = ref<HTMLElement | null>(null);

useGsap(root, ({ gsap }) => {
    gsap.utils.toArray<HTMLElement>('[data-parallax]').forEach((el) => {
        gsap.fromTo(el, { yPercent: Number(el.dataset.parallax) }, {
            yPercent: -Number(el.dataset.parallax),
            ease: 'none',
            scrollTrigger: { trigger: root.value, start: 'top bottom', end: 'bottom top', scrub: true },
        });
    });
    gsap.from('[data-spot-text] > *', {
        autoAlpha: 0, x: document.documentElement.dir === 'rtl' ? -40 : 40, duration: 0.8, stagger: 0.1, ease: 'power3.out',
        scrollTrigger: { trigger: '[data-spot-text]', start: 'top 80%', once: true },
    });
});
</script>

<template>
    <section ref="root" class="relative mt-28 overflow-hidden bg-cocoa py-20 text-white lg:py-28">
        <div class="absolute -top-40 start-1/3 size-[36rem] rounded-full bg-brand-600/25 blur-3xl" aria-hidden="true" />
        <div class="container-store relative grid items-center gap-14 lg:grid-cols-2">
            <div class="grid grid-cols-2 gap-4">
                <img
                    v-for="(src, i) in spotlight.images.slice(0, 4)"
                    :key="src"
                    :src="src"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    :data-parallax="[8, 14, 12, 6][i]"
                    :class="['aspect-[3/4] w-full rounded-[1.75rem] object-cover shadow-2xl ring-1 ring-white/10', i % 2 ? 'mt-12' : '']"
                />
            </div>
            <div data-spot-text>
                <span class="inline-flex items-center gap-2 text-sm font-semibold text-brand-300"><span class="h-px w-8 bg-brand-300" />{{ t('storefront.spotlight_eyebrow') }}</span>
                <h2 class="mt-4 text-4xl font-extrabold leading-snug sm:text-5xl">{{ spotlight.title }}</h2>
                <p v-if="spotlight.text" class="mt-5 max-w-lg text-lg leading-relaxed text-white/70">{{ spotlight.text }}</p>
                <Link :href="spotlight.url" class="btn mt-8 h-13 bg-white px-8 text-base text-cocoa hover:bg-brand-100">
                    {{ t('storefront.shop_now') }}
                    <Icon name="arrow" class="size-5 rtl:-scale-x-100" />
                </Link>
                <div v-if="spotlight.products.length" class="mt-10 grid grid-cols-2 gap-3 text-cocoa">
                    <ProductCard v-for="product in spotlight.products.slice(0, 2)" :key="product.id" :product="product" />
                </div>
            </div>
        </div>
    </section>
</template>
