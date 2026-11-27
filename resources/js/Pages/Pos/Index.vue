<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PosOrderRow from '@/Components/Pos/PosOrderRow.vue';
import { useTrans } from '@/Composables/useTrans';
import type { PosCompany, PosOrderSummary } from '@/types/pos';

const props = defineProps<{ companies: PosCompany[]; recent: PosOrderSummary[]; can: { create_company: boolean; edit_company: boolean } }>();
const { t } = useTrans();

const query = ref('');
const normalize = (v: string | null) => (v ?? '').toLocaleLowerCase().trim();
const filtered = computed(() => {
    const q = normalize(query.value);
    return q ? props.companies.filter((c) => [c.name, c.city, c.contact_name, c.phone].some((f) => normalize(f).includes(q))) : props.companies;
});
</script>

<template>
    <div class="mx-auto grid max-w-[90rem] gap-6 p-4 sm:p-6 lg:grid-cols-12">
        <section class="lg:col-span-8">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">{{ t('pos.choose_company') }}</h1>
                    <p class="mt-1 text-sm text-stone-500">{{ t('pos.subtitle') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-stone-500 tabular-nums">{{ filtered.length }} / {{ companies.length }}</span>
                    <Link v-if="can.create_company" :href="route('pos.companies.create')" class="btn-primary h-12 gap-2 px-5">
                        <span class="text-xl leading-none" aria-hidden="true">+</span>{{ t('pos.new_company') }}
                    </Link>
                </div>
            </div>

            <label class="relative mt-5 block">
                <span class="sr-only">{{ t('pos.search_companies') }}</span>
                <input v-model="query" type="search" class="field h-14 rounded-2xl ps-12 text-base" :placeholder="t('pos.search_companies')" autocomplete="off" />
                <svg viewBox="0 0 24 24" class="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-stone-400" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" stroke-linecap="round" /></svg>
            </label>

            <div v-if="filtered.length" class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div v-for="company in filtered" :key="company.id" class="relative">
                    <Link
                        :href="route('pos.create', company.id)"
                        class="group flex h-full min-h-28 flex-col justify-between rounded-2xl bg-white p-5 pe-14 ring-1 ring-stone-200 transition hover:ring-2 hover:ring-brand-500 active:scale-[0.99]"
                    >
                        <span class="text-lg font-bold leading-snug group-hover:text-brand-800">{{ company.name }}</span>
                        <span class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-sm text-stone-500">
                            <span v-if="company.city">{{ company.city }}</span>
                            <span v-if="company.contact_name">{{ company.contact_name }}</span>
                            <span v-if="company.phone" dir="ltr">{{ company.phone }}</span>
                        </span>
                    </Link>
                    <Link
                        v-if="can.edit_company"
                        :href="route('pos.companies.edit', company.id)"
                        class="absolute end-3 top-3 inline-flex size-11 items-center justify-center rounded-full text-stone-500 hover:bg-stone-100 hover:text-brand-700"
                        :aria-label="`${t('pos.edit_company')}: ${company.name}`"
                        :title="t('pos.edit_company')"
                    >
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 20h4L19 9l-4-4L4 16v4Z" stroke-linejoin="round" /><path d="m13.5 6.5 4 4" /></svg>
                    </Link>
                </div>
            </div>
            <p v-else class="mt-10 rounded-2xl bg-white p-10 text-center text-stone-500 ring-1 ring-stone-200">
                {{ companies.length ? t('common.no_results') : t('pos.no_companies') }}
            </p>
        </section>

        <aside class="lg:col-span-4">
            <div class="rounded-2xl bg-white p-5 ring-1 ring-stone-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold">{{ t('pos.recent_orders') }}</h2>
                    <Link :href="route('pos.orders')" class="text-sm font-semibold text-brand-700">{{ t('pos.my_orders') }}</Link>
                </div>
                <ul v-if="recent.length" class="mt-4 divide-y divide-stone-100">
                    <li v-for="order in recent" :key="order.id"><PosOrderRow :order="order" compact /></li>
                </ul>
                <p v-else class="mt-4 text-sm text-stone-500">{{ t('pos.no_orders') }}</p>
            </div>
        </aside>
    </div>
</template>
