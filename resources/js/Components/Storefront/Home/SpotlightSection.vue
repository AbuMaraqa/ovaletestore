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
    <section ref="root" class="container-store mt-24 lg:mt-28">
        <div class="grid items-center gap-14 overflow-hidden rounded-3xl bg-cocoa px-5 py-14 text-white sm:px-10 lg:grid-cols-2 lg:px-16 lg:py-20">
            <div class="grid grid-cols-2 gap-4">
                <img
                    v-for="(src, i) in spotlight.images.slice(0, 4)"
                    :key="src"
                    :src="src"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    :data-parallax="[8, 14, 12, 6][i]"
                    :class="['aspect-[3/4] w-full rounded-2xl object-cover', i % 2 ? 'mt-12' : '']"
                />
            </div>
            <div data-spot-text>
                <p class="text-sm font-semibold text-brand-300">{{ t('storefront.spotlight_eyebrow') }}</p>
                <h2 class="mt-4 text-4xl font-extrabold leading-snug sm:text-5xl">{{ spotlight.title }}</h2>
                <p v-if="spotlight.text" class="mt-5 max-w-lg text-lg leading-relaxed text-white/70">{{ spotlight.text }}</p>
                <Link :href="spotlight.url" class="btn mt-8 h-13 bg-white px-8 text-base text-cocoa hover:bg-sand">
                    {{ t('storefront.shop_now') }}
                    <Icon name="arrow" class="size-5 rtl:-scale-x-100" />
                </Link>
                <div v-if="spotlight.products.length" class="mt-10 grid grid-cols-2 gap-2.5 text-cocoa sm:grid-cols-3">
                    <ProductCard v-for="product in spotlight.products.slice(0, 6)" :key="product.id" :product="product" compact />
                </div>
            </div>
        </div>
    </section>
</template>
