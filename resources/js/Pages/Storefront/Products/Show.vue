<script setup lang="ts">
import { Deferred, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ProductGallery from '@/Components/Storefront/ProductGallery.vue';
import Icon from '@/Components/Storefront/Icon.vue';
import ProductGrid from '@/Components/Storefront/ProductGrid.vue';
import ProductPriceBlock from '@/Components/Storefront/ProductPriceBlock.vue';
import ProductTrust from '@/Components/Storefront/ProductTrust.vue';
import QuantityInput from '@/Components/Storefront/QuantityInput.vue';
import StickyBuyBar from '@/Components/Storefront/StickyBuyBar.vue';
import VariantPicker from '@/Components/Storefront/VariantPicker.vue';
import { useTrans } from '@/Composables/useTrans';
import { useVariantSelection } from '@/Composables/useVariantSelection';
import type { Image, ProductCard } from '@/types';

interface Variant {
    id: number;
    term_ids: number[];
    sku: string | null;
    price: string;
    regular_price: string | null;
    on_sale: boolean;
    discount_percent: number | null;
    saving: string | null;
    sale_ends_at: string | null;
    purchasable: boolean;
    max_quantity: number | null;
    stock_status: 'in_stock' | 'out_of_stock' | 'on_backorder';
    is_default: boolean;
    image: Image | null;
}

interface Attribute {
    id: number;
    name: string;
    display_type: 'button' | 'select' | 'color';
    used_for_variations: boolean;
    terms: { id: number; name: string; color: string | null }[];
}

const props = defineProps<{
    product: {
        id: number;
        name: string;
        short_description: string | null;
        description: string | null;
        is_variable: boolean;
        brand: { name: string } | null;
        category: { name: string; url: string } | null;
        images: Image[];
        attributes: Attribute[];
        variants: Variant[];
    };
    breadcrumbs: { name: string; url: string }[];
    related?: ProductCard[];
}>();

const { t } = useTrans();
const { variationAttributes, selected, variant, isAvailable, select } = useVariantSelection(props.product.attributes, props.product.variants);
const infoAttributes = computed(() => props.product.attributes.filter((a) => !a.used_for_variations));
const quantity = ref(1);
const adding = ref(false);

// A variant image (if any) leads the gallery when that variant is selected.
const galleryImages = computed(() => (variant.value?.image ? [variant.value.image, ...props.product.images] : props.product.images));
watch(variant, () => (quantity.value = 1));

function addToCart(): void {
    if (!variant.value?.purchasable) return;
    router.post(
        route('storefront.cart.store'),
        { variant_id: variant.value.id, quantity: quantity.value },
        { preserveScroll: true, onStart: () => (adding.value = true), onFinish: () => (adding.value = false) },
    );
}

const buyRow = ref<HTMLElement | null>(null);
const buyLabel = computed(() => (variant.value && !variant.value.purchasable ? t('storefront.out_of_stock') : t('storefront.add_to_cart')));
</script>

<template>
    <div class="container-store py-8 lg:py-12">
        <nav class="flex flex-wrap items-center gap-x-2 text-sm text-stone-500" aria-label="Breadcrumb">
            <template v-for="(crumb, i) in breadcrumbs" :key="crumb.url">
                <Link v-if="i < breadcrumbs.length - 1" :href="crumb.url" class="hover:text-cocoa">{{ crumb.name }}</Link>
                <span v-else class="line-clamp-1 text-cocoa" aria-current="page">{{ crumb.name }}</span>
                <span v-if="i < breadcrumbs.length - 1" aria-hidden="true">/</span>
            </template>
        </nav>

        <div class="mt-6 grid gap-10 lg:grid-cols-12 lg:gap-14">
            <ProductGallery :images="galleryImages" :name="product.name" class="lg:col-span-7" />

            <section class="lg:col-span-5">
                <div class="lg:sticky lg:top-32">
                    <p v-if="product.brand || product.category" class="text-sm font-medium text-brand-700">
                        <Link v-if="product.category" :href="product.category.url" class="hover:underline">{{ product.category.name }}</Link>
                        <span v-if="product.brand"> · {{ product.brand.name }}</span>
                    </p>
                    <h1 class="mt-2 text-2xl leading-[1.45] font-bold text-cocoa sm:text-3xl">{{ product.name }}</h1>

                    <ProductPriceBlock :variant="variant ?? null" />

                    <div v-if="product.short_description" class="rich-text mt-5 text-base" v-html="product.short_description" />

                    <VariantPicker
                        v-if="variationAttributes.length"
                        :attributes="product.attributes.filter((a) => a.used_for_variations)"
                        :selected="selected"
                        :is-available="isAvailable"
                        class="mt-7"
                        @select="select"
                    />

                    <div ref="buyRow" class="mt-8 flex flex-wrap items-center gap-3">
                        <QuantityInput v-model="quantity" :max="variant?.max_quantity ?? 999" :disabled="!variant?.purchasable" />
                        <button type="button" class="btn-accent h-13 flex-1 text-base" :disabled="!variant?.purchasable || adding" @click="addToCart">
                            <span v-if="adding" class="size-4 animate-spin rounded-full border-2 border-white border-e-transparent" aria-hidden="true" />
                            <Icon v-else name="cart" class="size-5" />
                            {{ buyLabel }}
                        </button>
                    </div>

                    <ProductTrust :product-name="product.name" />

                    <dl v-if="variant?.sku || infoAttributes.length" class="mt-6 divide-y divide-stone-200 rounded-2xl bg-white px-5 text-sm ring-1 ring-stone-100">
                        <div v-if="variant?.sku" class="flex justify-between gap-4 py-3">
                            <dt class="text-stone-600">{{ t('storefront.sku') }}</dt>
                            <dd class="font-mono text-xs" dir="ltr">{{ variant.sku }}</dd>
                        </div>
                        <div v-for="attribute in infoAttributes" :key="attribute.id" class="flex justify-between gap-4 py-3">
                            <dt class="text-stone-600">{{ attribute.name }}</dt>
                            <dd class="text-end">{{ attribute.terms.map((term) => term.name).join('، ') }}</dd>
                        </div>
                    </dl>
                </div>
            </section>
        </div>

        <section v-if="product.description" class="mt-16 max-w-3xl">
            <h2 class="text-xl font-bold text-cocoa">{{ t('storefront.description') }}</h2>
            <!-- Sanitized on write (App\Support\RichText) -->
            <div class="rich-text mt-4" v-html="product.description" />
        </section>

        <section class="mt-20">
            <h2 class="text-2xl font-bold text-cocoa">{{ t('storefront.related_products') }}</h2>
            <Deferred data="related">
                <template #fallback>
                    <div class="mt-8 grid grid-cols-2 gap-5 md:grid-cols-3 xl:grid-cols-4">
                        <div v-for="n in 4" :key="n" class="aspect-[3/4] animate-pulse rounded-card bg-stone-200/60" />
                    </div>
                </template>
                <ProductGrid :products="related ?? []" class="mt-8" />
            </Deferred>
        </section>

        <StickyBuyBar :target="buyRow" :price="variant?.price ?? null" :disabled="!variant?.purchasable" :adding="adding" :label="buyLabel" @add="addToCart" />
    </div>
</template>
