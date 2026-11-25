<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';
import ResponsiveImage from '@/Components/Common/ResponsiveImage.vue';
import Icon from '@/Components/Storefront/Icon.vue';
import { useTrans } from '@/Composables/useTrans';
import type { ProductCard } from '@/types';

/**
 * Product card, photo first: the image sits on a soft cream tile (white product shots blend into it),
 * then category → name (2 lines) → price, with a round cart button beside the price instead of a heavy
 * full-width bar. Fixed row heights keep every card in a row identical; the card fills its cell (h-full).
 */
const props = withDefaults(defineProps<{ product: ProductCard; priority?: boolean }>(), { priority: false });
const { t } = useTrans();
const adding = ref(false);
const added = ref(false);
let addedTimer: number | undefined;

function quickAdd(): void {
    if (!props.product.default_variant_id || adding.value) return;
    router.post(
        route('storefront.cart.store'),
        { variant_id: props.product.default_variant_id, quantity: 1 },
        {
            preserveScroll: true,
            onStart: () => (adding.value = true),
            onSuccess: () => {
                added.value = true;
                window.clearTimeout(addedTimer);
                addedTimer = window.setTimeout(() => (added.value = false), 1600);
            },
            onFinish: () => (adding.value = false),
        },
    );
}
onBeforeUnmount(() => window.clearTimeout(addedTimer));
</script>

<template>
    <article class="group relative flex h-full w-full flex-col rounded-[1.25rem] bg-white p-2 ring-1 ring-stone-200/70 transition duration-300 hover:-translate-y-1 hover:shadow-lift hover:ring-stone-200">
        <Link :href="product.url" class="relative block aspect-square overflow-hidden rounded-2xl bg-[#f6f1ea]" :aria-label="product.name" tabindex="-1">
            <ResponsiveImage
                :image="product.image"
                :priority="priority"
                :alt="product.name"
                sizes="(min-width: 1024px) 18vw, (min-width: 768px) 24vw, (min-width: 640px) 31vw, 46vw"
                img-class="size-full object-contain p-4 mix-blend-multiply transition duration-500 group-hover:scale-105"
            />
            <span v-if="product.trade_price" class="absolute start-2 top-2 rounded-full bg-cocoa px-2.5 py-1 text-xs font-bold text-white">{{ t('storefront.trade_price') }}</span>
            <span v-else-if="product.on_sale" class="absolute start-2 top-2 rounded-full bg-brand-600 px-2.5 py-1 text-xs font-bold text-white" dir="ltr">
                {{ product.discount_percent ? t('storefront.discount_badge', { percent: product.discount_percent }) : t('storefront.sale') }}
            </span>
            <span v-if="!product.in_stock" class="absolute inset-x-2 bottom-2 rounded-xl bg-stone-900/75 py-1.5 text-center text-xs font-semibold text-white backdrop-blur">
                {{ t('storefront.out_of_stock') }}
            </span>
        </Link>

        <div class="flex flex-1 flex-col px-1.5 pt-3 pb-1">
            <p class="h-[1.125rem] truncate text-xs leading-[1.125rem] font-medium text-brand-700/80">{{ product.category }}</p>
            <h3 class="mt-1 line-clamp-2 h-[3.1em] text-[15px] leading-[1.55] font-semibold text-stone-800">
                <Link :href="product.url" class="hover:text-brand-700">{{ product.name }}</Link>
            </h3>

            <div class="mt-auto flex items-end justify-between gap-2 pt-3">
                <p class="flex min-w-0 flex-col leading-tight" dir="ltr">
                    <span v-if="product.regular_price" class="text-xs text-stone-500 line-through">{{ product.regular_price }}</span>
                    <span class="truncate text-lg font-extrabold sm:text-xl" :class="product.on_sale || product.trade_price ? 'text-brand-700' : 'text-cocoa'">
                        <template v-if="product.price_max">{{ t('storefront.from_price', { price: product.price }) }}</template>
                        <template v-else>{{ product.price }}</template>
                    </span>
                </p>

                <button
                    v-if="product.default_variant_id && product.in_stock"
                    type="button"
                    :class="[
                        'relative inline-flex size-11 shrink-0 items-center justify-center rounded-full text-white transition duration-300 active:scale-95 disabled:opacity-70',
                        added ? 'bg-emerald-600' : 'bg-cocoa hover:bg-brand-700',
                    ]"
                    :disabled="adding"
                    :aria-label="`${t('storefront.add_to_cart')}: ${product.name}`"
                    :title="t('storefront.add_to_cart')"
                    @click="quickAdd"
                >
                    <span v-if="adding" class="size-4 animate-spin rounded-full border-2 border-white border-e-transparent" aria-hidden="true" />
                    <Icon v-else-if="added" name="check" class="size-5" />
                    <Icon v-else name="cart" class="size-5" />
                </button>
                <Link
                    v-else-if="product.is_variable && product.in_stock"
                    :href="product.url"
                    class="inline-flex size-11 shrink-0 items-center justify-center rounded-full text-cocoa ring-1 ring-cocoa/25 transition hover:bg-cocoa hover:text-white"
                    :aria-label="`${t('storefront.select_options')}: ${product.name}`"
                    :title="t('storefront.select_options')"
                >
                    <Icon name="arrow" class="size-5 rtl:-scale-x-100" />
                </Link>
            </div>
        </div>
    </article>
</template>
