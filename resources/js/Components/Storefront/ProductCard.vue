<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ResponsiveImage from '@/Components/Common/ResponsiveImage.vue';
import Icon from '@/Components/Storefront/Icon.vue';
import { useTrans } from '@/Composables/useTrans';
import type { ProductCard } from '@/types';

/**
 * Product card: image → name → price (with "-X%" saving) → category → full-width "Add to cart".
 * Sized for Arabic readability (Tajawal has a small x-height) and 44px touch targets.
 */
const props = withDefaults(defineProps<{ product: ProductCard; priority?: boolean }>(), { priority: false });
const { t } = useTrans();
const adding = ref(false);

function quickAdd(): void {
    if (!props.product.default_variant_id) return;
    router.post(
        route('storefront.cart.store'),
        { variant_id: props.product.default_variant_id, quantity: 1 },
        { preserveScroll: true, onStart: () => (adding.value = true), onFinish: () => (adding.value = false) },
    );
}
</script>

<template>
    <article class="group relative flex flex-col rounded-2xl border border-stone-200/80 bg-white p-2.5 transition duration-300 hover:-translate-y-1 hover:border-stone-200 hover:shadow-lift">
        <Link :href="product.url" class="relative block aspect-square overflow-hidden rounded-xl bg-white" :aria-label="product.name" tabindex="-1">
            <ResponsiveImage
                :image="product.image"
                :priority="priority"
                :alt="product.name"
                sizes="(min-width: 1024px) 18vw, (min-width: 768px) 24vw, (min-width: 640px) 31vw, 46vw"
                img-class="size-full object-contain p-1.5 transition duration-500 group-hover:scale-105"
            />
            <span v-if="product.on_sale" class="absolute start-1.5 top-1.5 rounded-full bg-brand-600 px-2 py-1 text-xs font-bold text-white shadow-sm" dir="ltr">
                {{ product.discount_percent ? t('storefront.discount_badge', { percent: product.discount_percent }) : t('storefront.sale') }}
            </span>
            <span v-if="!product.in_stock" class="absolute inset-x-1.5 bottom-1.5 rounded-lg bg-stone-900/80 py-1 text-center text-xs font-semibold text-white backdrop-blur">
                {{ t('storefront.out_of_stock') }}
            </span>
        </Link>

        <div class="mt-3 flex flex-1 flex-col px-1">
            <h3 class="line-clamp-2 min-h-[3.1em] text-[15px] leading-[1.55] font-semibold text-stone-800 sm:text-base">
                <Link :href="product.url" class="hover:text-brand-700">{{ product.name }}</Link>
            </h3>

            <p class="mt-2 flex flex-wrap items-baseline gap-x-2 leading-tight">
                <span class="text-lg font-extrabold sm:text-xl" :class="product.on_sale ? 'text-brand-700' : 'text-cocoa'" dir="ltr">
                    <template v-if="product.price_max">{{ t('storefront.from_price', { price: product.price }) }}</template>
                    <template v-else>{{ product.price }}</template>
                </span>
                <span v-if="product.regular_price" class="text-[13px] text-stone-500 line-through" dir="ltr">{{ product.regular_price }}</span>
            </p>
            <p v-if="product.category" class="mt-1 truncate text-xs text-stone-500">{{ product.category }}</p>

            <div class="mt-auto pt-3">
                <button
                    v-if="product.default_variant_id && product.in_stock"
                    type="button"
                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-full bg-cocoa text-sm font-bold text-white transition hover:bg-brand-700 disabled:opacity-60"
                    :disabled="adding"
                    @click="quickAdd"
                >
                    <span v-if="adding" class="size-4 animate-spin rounded-full border-2 border-white border-e-transparent" aria-hidden="true" />
                    <Icon v-else name="cart" class="size-4" />
                    {{ t('storefront.add_to_cart') }}
                </button>
                <Link
                    v-else-if="product.is_variable && product.in_stock"
                    :href="product.url"
                    class="inline-flex h-11 w-full items-center justify-center rounded-full text-sm font-bold text-cocoa ring-1 ring-cocoa transition hover:bg-cocoa hover:text-white"
                >
                    {{ t('storefront.select_options') }}
                </Link>
                <span v-else class="inline-flex h-11 w-full items-center justify-center rounded-full bg-stone-100 text-sm font-semibold text-stone-500">{{ t('storefront.out_of_stock') }}</span>
            </div>
        </div>
    </article>
</template>
