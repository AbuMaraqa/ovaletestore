<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AccountShell from '@/Components/Storefront/AccountShell.vue';
import OrderStatusPill from '@/Components/Storefront/OrderStatusPill.vue';
import StoreField from '@/Components/Storefront/StoreField.vue';
import { useTrans } from '@/Composables/useTrans';

type OrderSummary = { number: string; status: string; total: string; items_count: number; date: string; url: string };
const props = defineProps<{
    profile: { name: string; email: string; phone: string | null };
    stats: { orders: number; open: number; spent: string };
    recentOrders: OrderSummary[];
}>();

const { t } = useTrans();
const form = useForm({ name: props.profile.name, email: props.profile.email, phone: props.profile.phone ?? '', current_password: '' });
// The server asks for the current password only when the e-mail changes.
const emailChanged = computed(() => form.email.trim().toLowerCase() !== props.profile.email.toLowerCase());
const saveProfile = () => form.put(route('account.profile.update'), { preserveScroll: true, onSuccess: () => form.reset('current_password') });
const password = useForm({ current_password: '', password: '', password_confirmation: '' });
const savePassword = () => password.put(route('user-password.update'), { errorBag: 'updatePassword', preserveScroll: true, onSuccess: () => password.reset() });
</script>

<template>
    <AccountShell :title="t('account.overview')">
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-3xl bg-white p-5 ring-1 ring-stone-200/70"><p class="text-sm text-stone-500">{{ t('account.orders_total') }}</p><p class="mt-1 text-2xl font-extrabold text-cocoa tabular-nums">{{ stats.orders }}</p></div>
            <div class="rounded-3xl bg-white p-5 ring-1 ring-stone-200/70"><p class="text-sm text-stone-500">{{ t('account.orders_open') }}</p><p class="mt-1 text-2xl font-extrabold text-cocoa tabular-nums">{{ stats.open }}</p></div>
            <div class="rounded-3xl bg-cocoa p-5 text-white"><p class="text-sm text-white/70">{{ t('account.total_spent') }}</p><p class="mt-1 text-2xl font-extrabold tabular-nums" dir="ltr">{{ stats.spent }}</p></div>
        </div>

        <section class="mt-8 rounded-3xl bg-white p-5 ring-1 ring-stone-200/70 sm:p-6">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-cocoa">{{ t('account.recent_orders') }}</h2>
                <Link :href="route('account.orders')" class="text-sm font-semibold text-brand-700 hover:underline">{{ t('storefront.view_all') }}</Link>
            </div>
            <ul v-if="recentOrders.length" class="mt-4 divide-y divide-stone-100">
                <li v-for="o in recentOrders" :key="o.number">
                    <Link :href="o.url" class="flex flex-wrap items-center justify-between gap-3 py-3.5 hover:text-brand-700">
                        <span><span class="font-mono font-bold" dir="ltr">{{ o.number }}</span> <span class="ms-2 text-sm text-stone-500">{{ o.date }}</span></span>
                        <span class="flex items-center gap-3"><OrderStatusPill :status="o.status" /><b dir="ltr">{{ o.total }}</b></span>
                    </Link>
                </li>
            </ul>
            <div v-else class="py-10 text-center">
                <p class="text-stone-500">{{ t('account.no_orders') }}</p>
                <Link :href="route('storefront.shop')" class="btn-primary mt-5">{{ t('storefront.hero_cta') }}</Link>
            </div>
        </section>

        <div class="mt-8 grid gap-6 xl:grid-cols-2">
            <form class="rounded-3xl bg-white p-5 ring-1 ring-stone-200/70 sm:p-6" @submit.prevent="saveProfile">
                <h2 class="text-lg font-bold text-cocoa">{{ t('account.profile') }}</h2>
                <div class="mt-5 space-y-4">
                    <StoreField id="name" v-model="form.name" :label="t('cart.name')" :error="form.errors.name" required />
                    <StoreField id="email" v-model="form.email" type="email" :label="t('common.email')" :error="form.errors.email" dir="ltr" required />
                    <StoreField id="phone" v-model="form.phone" type="tel" :label="t('cart.phone')" :error="form.errors.phone" dir="ltr" required />
                    <StoreField v-if="emailChanged" id="profile_current_password" v-model="form.current_password" type="password" :label="t('account.current_password')" :error="form.errors.current_password" autocomplete="current-password" required />
                </div>
                <button type="submit" class="btn-primary mt-5" :disabled="form.processing">{{ t('common.save') }}</button>
            </form>

            <form class="rounded-3xl bg-white p-5 ring-1 ring-stone-200/70 sm:p-6" @submit.prevent="savePassword">
                <h2 class="text-lg font-bold text-cocoa">{{ t('account.change_password') }}</h2>
                <div class="mt-5 space-y-4">
                    <StoreField id="current_password" v-model="password.current_password" type="password" :label="t('account.current_password')" :error="password.errors.current_password" autocomplete="current-password" required />
                    <StoreField id="new_password" v-model="password.password" type="password" :label="t('common.password')" :error="password.errors.password" autocomplete="new-password" required />
                    <StoreField id="new_password_confirmation" v-model="password.password_confirmation" type="password" :label="t('common.confirm_password')" autocomplete="new-password" required />
                </div>
                <button type="submit" class="btn-ghost mt-5" :disabled="password.processing">{{ t('common.save') }}</button>
            </form>
        </div>
    </AccountShell>
</template>
