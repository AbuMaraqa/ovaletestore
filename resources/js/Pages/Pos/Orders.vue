<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import PosOrderRow from '@/Components/Pos/PosOrderRow.vue';
import Pagination from '@/Components/Storefront/Pagination.vue';
import { useTrans } from '@/Composables/useTrans';
import type { PosOrderSummary } from '@/types/pos';
import type { PaginationLink } from '@/types';

/** The rep's orders with search (number, company, phone), status and date filters, kept in the URL. */
type Filters = { q: string; status: string; from: string; until: string };
const props = defineProps<{ orders: { data: PosOrderSummary[]; links: PaginationLink[] }; filters: Filters; statuses: string[] }>();
const { t } = useTrans();

const state = reactive<Filters>({ ...props.filters });
const active = computed(() => Boolean(state.q || state.status || state.from || state.until));

let timer: number | undefined;
function apply(): void {
    window.clearTimeout(timer);
    const params = Object.fromEntries(Object.entries(state).filter(([, v]) => v !== ''));
    router.get(route('pos.orders'), params, { preserveState: true, preserveScroll: true, replace: true });
}
// Typing searches after a short pause; the other filters apply at once.
watch(() => state.q, () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(apply, 350);
});
watch(() => [state.status, state.from, state.until], apply);
function reset(): void {
    Object.assign(state, { q: '', status: '', from: '', until: '' });
}
</script>

<template>
    <div class="mx-auto max-w-4xl p-4 sm:p-6">
        <h1 class="text-2xl font-bold">{{ t('pos.my_orders') }}</h1>

        <div class="mt-5 grid gap-3 rounded-2xl bg-white p-4 ring-1 ring-stone-200 sm:grid-cols-12">
            <label class="relative block sm:col-span-12 lg:col-span-5">
                <span class="sr-only">{{ t('pos.search_orders') }}</span>
                <input v-model="state.q" type="search" class="field h-12 rounded-xl ps-11 text-base" :placeholder="t('pos.search_orders')" autocomplete="off" />
                <svg viewBox="0 0 24 24" class="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-stone-400" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" stroke-linecap="round" /></svg>
            </label>
            <label class="block sm:col-span-4 lg:col-span-3">
                <span class="sr-only">{{ t('pos.status') }}</span>
                <select v-model="state.status" class="field h-12 rounded-xl text-base">
                    <option value="">{{ t('pos.all_statuses') }}</option>
                    <option v-for="s in statuses" :key="s" :value="s">{{ t(`orders.status_${s}`) }}</option>
                </select>
            </label>
            <label class="block sm:col-span-4 lg:col-span-2">
                <span class="sr-only">{{ t('pos.date_from') }}</span>
                <input v-model="state.from" type="date" class="field h-12 rounded-xl text-sm" :aria-label="t('pos.date_from')" :title="t('pos.date_from')" />
            </label>
            <label class="block sm:col-span-4 lg:col-span-2">
                <span class="sr-only">{{ t('pos.date_until') }}</span>
                <input v-model="state.until" type="date" class="field h-12 rounded-xl text-sm" :aria-label="t('pos.date_until')" :title="t('pos.date_until')" />
            </label>
            <div v-if="active" class="flex justify-end sm:col-span-12">
                <button type="button" class="text-sm font-semibold text-brand-700 hover:underline" @click="reset">{{ t('pos.clear_filters') }}</button>
            </div>
        </div>

        <div class="mt-4 overflow-hidden rounded-2xl bg-white ring-1 ring-stone-200">
            <ul v-if="orders.data.length" class="divide-y divide-stone-100">
                <li v-for="order in orders.data" :key="order.id"><PosOrderRow :order="order" /></li>
            </ul>
            <p v-else class="p-10 text-center text-stone-500">{{ active ? t('common.no_results') : t('pos.no_orders') }}</p>
        </div>
        <Pagination class="mt-6" :links="orders.links" />
    </div>
</template>
