<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import PosCartLine from '@/Components/Pos/PosCartLine.vue';
import PosProductTile from '@/Components/Pos/PosProductTile.vue';
import { usePosOrder } from '@/Composables/usePosOrder';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';
import type { PosCatalog, PosCompany } from '@/types/pos';

const props = defineProps<{ company: PosCompany; catalog: PosCatalog; can_edit_company: boolean }>();
const page = usePage<SharedProps>();
const { t } = useTrans();

const order = usePosOrder(props.company.id, props.catalog.items, page.props.site.currency, page.props.locale.current);

// --- Browsing: search + category, rendered in pages of 60 for a light DOM on the iPad.
const query = ref('');
const category = ref<number | null>(null);
const PAGE = 60;
const visible = ref(PAGE);
const normalize = (v: string | null) => (v ?? '').toLocaleLowerCase().replace(/\s+/g, ' ').trim();
const index = props.catalog.items.map((item) => ({ item, haystack: normalize(`${item.name} ${item.variant ?? ''} ${item.sku ?? ''}`) }));

const filtered = computed(() => {
    const words = normalize(query.value).split(' ').filter(Boolean);
    return index
        .filter(({ item, haystack }) => (category.value === null || item.categories.includes(category.value)) && words.every((w) => haystack.includes(w)))
        .map(({ item }) => item);
});
const shown = computed(() => filtered.value.slice(0, visible.value));
watch([query, category], () => (visible.value = PAGE));

const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;
watch(sentinel, (el) => {
    observer?.disconnect();
    if (!el) return;
    observer = new IntersectionObserver((entries) => {
        if (entries.some((e) => e.isIntersecting)) visible.value += PAGE;
    }, { rootMargin: '600px' });
    observer.observe(el);
});
onBeforeUnmount(() => observer?.disconnect());

// --- Submit.
const form = useForm({ company_id: props.company.id, items: [] as { variant_id: number; quantity: number }[], note: '' });
const errors = computed(() => Object.values(form.errors as Record<string, string>));

function submit(): void {
    if (!order.lines.value.length || form.processing) return;
    form.items = order.payload();
    form.post(route('pos.orders.store'), {
        preserveScroll: true,
        onSuccess: () => order.clear(),
    });
}

function clearOrder(): void {
    if (window.confirm(t('pos.confirm_clear'))) order.clear();
}

const cartPanel = ref<HTMLElement | null>(null);
async function focusCart(): Promise<void> {
    await nextTick();
    cartPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <div class="grid min-h-[calc(100dvh-4rem)] md:grid-cols-12">
        <!-- Catalog -->
        <section class="p-4 sm:p-5 md:col-span-7 xl:col-span-8">
            <div class="sticky top-16 z-20 -mx-4 -mt-4 bg-stone-100/95 px-4 pt-4 pb-3 backdrop-blur sm:-mx-5 sm:-mt-5 sm:px-5 sm:pt-5">
                <div class="flex items-center gap-3">
                    <Link :href="route('pos.index')" class="btn-ghost h-12 shrink-0 px-4">{{ t('pos.change_company') }}</Link>
                    <label class="relative block flex-1">
                        <span class="sr-only">{{ t('pos.search_products') }}</span>
                        <input v-model="query" type="search" class="field h-12 rounded-2xl ps-11 text-base" :placeholder="t('pos.search_products')" autocomplete="off" />
                        <svg viewBox="0 0 24 24" class="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-stone-400" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" stroke-linecap="round" /></svg>
                    </label>
                </div>
                <div class="-mx-1 mt-3 flex gap-2 overflow-x-auto px-1 pb-1" role="tablist">
                    <button
                        type="button"
                        class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold ring-1 transition"
                        :class="category === null ? 'bg-cocoa text-white ring-cocoa' : 'bg-white ring-stone-200'"
                        @click="category = null"
                    >
                        {{ t('pos.all_categories') }}
                    </button>
                    <button
                        v-for="c in catalog.categories"
                        :key="c.id"
                        type="button"
                        class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold ring-1 transition"
                        :class="category === c.id ? 'bg-cocoa text-white ring-cocoa' : 'bg-white ring-stone-200'"
                        @click="category = c.id"
                    >
                        {{ c.name }}
                    </button>
                </div>
            </div>

            <div v-if="shown.length" class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                <PosProductTile
                    v-for="item in shown"
                    :key="item.id"
                    :item="item"
                    :quantity="order.quantities.get(item.id) ?? 0"
                    :disabled="order.atMax(item.id)"
                    @add="order.add(item.id)"
                />
            </div>
            <p v-else class="mt-10 text-center text-stone-500">{{ t('pos.no_products') }}</p>
            <div v-if="shown.length < filtered.length" ref="sentinel" class="h-10" aria-hidden="true" />
        </section>

        <!-- Order panel -->
        <aside ref="cartPanel" class="border-stone-200 bg-white md:col-span-5 md:border-s xl:col-span-4">
            <div class="flex flex-col md:sticky md:top-16 md:h-[calc(100dvh-4rem)]">
                <div class="border-b border-stone-100 p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">{{ t('pos.company') }}</p>
                    <h1 class="mt-1 text-xl font-bold leading-snug">{{ company.name }}</h1>
                    <Link v-if="can_edit_company" :href="route('pos.companies.edit', company.id)" class="mt-1 inline-block text-sm font-semibold text-brand-700 hover:underline">{{ t('pos.edit_company') }}</Link>
                    <p class="mt-1 text-sm text-stone-500">
                        <span v-if="company.city">{{ company.city }}</span>
                        <span v-if="company.phone" dir="ltr"> · {{ company.phone }}</span>
                    </p>
                </div>

                <div class="flex-1 overflow-y-auto px-5">
                    <div class="flex items-center justify-between pt-4">
                        <h2 class="font-bold">{{ t('pos.order_items') }} <span class="text-stone-500 tabular-nums">({{ order.unitCount.value }})</span></h2>
                        <button v-if="order.lines.value.length" type="button" class="text-sm font-semibold text-red-600" @click="clearOrder">{{ t('pos.clear') }}</button>
                    </div>
                    <div v-if="order.lines.value.length" class="divide-y divide-stone-100">
                        <PosCartLine
                            v-for="line in order.lines.value"
                            :key="line.item.id"
                            :item="line.item"
                            :quantity="line.quantity"
                            :line-total="order.format(line.item.price * line.quantity)"
                            :at-max="order.atMax(line.item.id)"
                            @set="(qty) => order.set(line.item.id, qty)"
                        />
                    </div>
                    <p v-else class="py-10 text-center text-sm text-stone-500">{{ t('pos.empty_order') }}</p>
                </div>

                <div class="space-y-3 border-t border-stone-100 p-5">
                    <label class="block">
                        <span class="sr-only">{{ t('pos.note') }}</span>
                        <textarea v-model="form.note" rows="2" maxlength="1000" class="field resize-none" :placeholder="t('pos.note_placeholder')" />
                    </label>
                    <ul v-if="errors.length" class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                        <li v-for="(message, i) in errors" :key="i">{{ message }}</li>
                    </ul>
                    <div class="flex items-baseline justify-between">
                        <span class="text-stone-500">{{ t('pos.total') }} <span class="text-xs">({{ t('pos.on_account') }})</span></span>
                        <span class="text-2xl font-bold tabular-nums" dir="ltr">{{ order.format(order.totalMinor.value) }}</span>
                    </div>
                    <button type="button" class="btn-accent h-14 w-full text-lg" :disabled="!order.lines.value.length || form.processing" @click="submit">
                        {{ form.processing ? t('pos.sending') : t('pos.send_order') }}
                    </button>
                </div>
            </div>
        </aside>

        <!-- Phone-width shortcut to the order panel -->
        <button
            v-if="order.lines.value.length"
            type="button"
            class="btn-primary fixed inset-x-4 bottom-4 z-30 h-14 text-base shadow-lift md:hidden"
            @click="focusCart"
        >
            {{ t('pos.order_items') }} · {{ order.unitCount.value }} · <span dir="ltr">{{ order.format(order.totalMinor.value) }}</span>
        </button>
    </div>
</template>
