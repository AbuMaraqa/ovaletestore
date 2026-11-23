<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ResponsiveImage from '@/Components/Common/ResponsiveImage.vue';
import Icon from '@/Components/Storefront/Icon.vue';
import { useTrans } from '@/Composables/useTrans';
import type { ProductCard } from '@/types';

/**
 * Compact product card, same structure as the original store (image → name → price → category →
 * full-width "Add to cart"), refined: hover lift, sale/out-of-stock badges, loading state.
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
            <span v-if="product.on_sale" class="absolute start-1.5 top-1.5 rounded-md bg-brand-600 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ t('storefront.sale') }}</span>
            <span v-if="!product.in_stock" class="absolute inset-x-1.5 bottom-1.5 rounded-md bg-stone-900/75 py-0.5 text-center text-[11px] font-semibold text-white backdrop-blur">
                {{ t('storefront.out_of_stock') }}
            </span>
        </Link>

        <div class="mt-2.5 flex flex-1 flex-col px-0.5">
            <h3 class="line-clamp-2 min-h-[2.5rem] text-[13px] font-semibold leading-snug text-stone-800">
                <Link :href="product.url" class="hover:text-brand-700 focus:outline-none">{{ product.name }}</Link>
            </h3>

            <p class="mt-2 flex flex-wrap items-baseline gap-x-1.5 leading-tight">
                <span class="text-[15px] font-extrabold text-cocoa" dir="ltr">
                    <template v-if="product.price_max">{{ t('storefront.from_price', { price: product.price }) }}</template>
                    <template v-else>{{ product.price }}</template>
                </span>
                <span v-if="product.regular_price" class="text-[11px] text-stone-400 line-through" dir="ltr">{{ product.regular_price }}</span>
            </p>
            <p v-if="product.category" class="mt-1 truncate text-[11px] text-stone-400">{{ product.category }}</p>

            <div class="mt-auto pt-2.5">
                <button
                    v-if="product.default_variant_id && product.in_stock"
                    type="button"
                    class="inline-flex h-9 w-full items-center justify-center gap-1.5 rounded-full bg-cocoa text-xs font-bold text-white transition hover:bg-brand-700 disabled:opacity-60"
                    :disabled="adding"
                    @click="quickAdd"
                >
                    <span v-if="adding" class="size-3.5 animate-spin rounded-full border-2 border-white border-e-transparent" aria-hidden="true" />
                    <Icon v-else name="cart" class="size-4" />
                    {{ t('storefront.add_to_cart') }}
                </button>
                <Link
                    v-else-if="product.is_variable && product.in_stock"
                    :href="product.url"
                    class="inline-flex h-9 w-full items-center justify-center rounded-full text-xs font-bold text-cocoa ring-1 ring-cocoa transition hover:bg-cocoa hover:text-white"
                >
                    {{ t('storefront.select_options') }}
                </Link>
                <span v-else class="inline-flex h-9 w-full items-center justify-center rounded-full bg-stone-100 text-xs font-semibold text-stone-400">{{ t('storefront.out_of_stock') }}</span>
            </div>
        </div>
    </article>
</template>
