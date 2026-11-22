<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ResponsiveImage from '@/Components/Common/ResponsiveImage.vue';
import Icon from '@/Components/Storefront/Icon.vue';
import { useTrans } from '@/Composables/useTrans';
import type { ProductCard } from '@/types';

const props = withDefaults(defineProps<{ product: ProductCard; priority?: boolean }>(), { priority: false });
const { t } = useTrans();
const adding = ref(false);

/** Simple products add straight from the card; variable products open the product page to choose. */
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
    <article class="group relative flex flex-col overflow-hidden rounded-card bg-white shadow-card ring-1 ring-stone-100 transition duration-300 hover:-translate-y-0.5 hover:shadow-lift">
        <Link :href="product.url" class="relative block aspect-square overflow-hidden bg-white" :aria-label="product.name" tabindex="-1">
            <ResponsiveImage
                :image="product.image"
                :priority="priority"
                :alt="product.name"
                sizes="(min-width: 1280px) 22vw, (min-width: 768px) 30vw, 48vw"
                img-class="size-full object-contain p-4 transition duration-500 group-hover:scale-[1.04]"
            />
            <span v-if="product.on_sale" class="absolute start-3 top-3 rounded-full bg-brand-600 px-2.5 py-1 text-[11px] font-bold text-white">{{ t('storefront.sale') }}</span>
            <span v-if="!product.in_stock" class="absolute inset-x-3 bottom-3 rounded-full bg-white/90 py-1 text-center text-xs font-semibold text-stone-500 backdrop-blur">
                {{ t('storefront.out_of_stock') }}
            </span>
        </Link>

        <div class="flex flex-1 flex-col gap-3 p-4">
            <h3 class="line-clamp-2 min-h-[2.75rem] text-sm font-medium leading-snug text-stone-800">
                <Link :href="product.url" class="after:absolute after:inset-0 focus:outline-none">{{ product.name }}</Link>
            </h3>

            <div class="mt-auto flex items-end justify-between gap-2">
                <p class="flex flex-col leading-tight">
                    <span v-if="product.regular_price" class="text-xs text-stone-400 line-through" dir="ltr">{{ product.regular_price }}</span>
                    <span class="text-base font-bold text-cocoa" dir="ltr">
                        <template v-if="product.price_max">{{ t('storefront.from_price', { price: product.price }) }}</template>
                        <template v-else>{{ product.price }}</template>
                    </span>
                </p>

                <button
                    v-if="product.default_variant_id && product.in_stock"
                    type="button"
                    class="relative z-10 inline-flex size-10 items-center justify-center rounded-full bg-cocoa text-white transition hover:bg-brand-700 disabled:opacity-60"
                    :disabled="adding"
                    :aria-label="`${t('storefront.add_to_cart')}: ${product.name}`"
                    @click="quickAdd"
                >
                    <Icon name="plus" class="size-5" />
                </button>
                <Link
                    v-else-if="product.is_variable"
                    :href="product.url"
                    class="relative z-10 rounded-full px-3 py-2 text-xs font-semibold text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50"
                >
                    {{ t('storefront.select_options') }}
                </Link>
            </div>
        </div>
    </article>
</template>
