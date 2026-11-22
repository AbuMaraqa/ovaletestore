<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import OrderSummary from '@/Components/Storefront/OrderSummary.vue';
import StoreField from '@/Components/Storefront/StoreField.vue';
import { useTrans } from '@/Composables/useTrans';
import type { CartPayload } from '@/types/cart';

const props = defineProps<{
    cart: CartPayload;
    prefill: { customer_name: string; customer_email: string | null; customer_phone: string | null } | null;
}>();

const { t } = useTrans();
const honeypot = usePage().props.honeypot as { nameFieldName: string; validFromFieldName: string; encryptedValidFrom: string; enabled: boolean } | undefined;

const form = useForm({
    customer_name: props.prefill?.customer_name ?? '',
    customer_phone: props.prefill?.customer_phone ?? '',
    customer_email: props.prefill?.customer_email ?? '',
    shipping_city: '',
    shipping_address: '',
    customer_note: '',
    ...(honeypot?.enabled ? { [honeypot.nameFieldName]: '', [honeypot.validFromFieldName]: honeypot.encryptedValidFrom } : {}),
});

const cartError = computed(() => (form.errors as Record<string, string>).cart);

function submit(): void {
    form.post(route('storefront.checkout.store'), { preserveScroll: true });
}
</script>

<template>
    <div class="container-store py-10 lg:py-14">
        <h1 class="text-3xl font-bold tracking-tight text-cocoa">{{ t('cart.checkout_title') }}</h1>

        <form class="mt-8 grid gap-8 lg:grid-cols-12" novalidate @submit.prevent="submit">
            <div class="space-y-8 lg:col-span-7">
                <p v-if="cartError" class="rounded-2xl bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-100" role="alert">{{ cartError }}</p>

                <fieldset class="rounded-[2rem] bg-white p-6 ring-1 ring-stone-100 sm:p-8">
                    <legend class="sr-only">{{ t('cart.contact_details') }}</legend>
                    <h2 class="text-lg font-semibold text-cocoa">{{ t('cart.contact_details') }}</h2>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <StoreField id="customer_name" v-model="form.customer_name" :label="t('cart.name')" :error="form.errors.customer_name" autocomplete="name" required class="sm:col-span-2" />
                        <StoreField id="customer_phone" v-model="form.customer_phone" :label="t('cart.phone')" :error="form.errors.customer_phone" type="tel" autocomplete="tel" inputmode="tel" dir="ltr" required />
                        <StoreField id="customer_email" v-model="form.customer_email" :label="t('cart.email_optional')" :error="form.errors.customer_email" type="email" autocomplete="email" dir="ltr" />
                    </div>
                </fieldset>

                <fieldset class="rounded-[2rem] bg-white p-6 ring-1 ring-stone-100 sm:p-8">
                    <legend class="sr-only">{{ t('cart.delivery_details') }}</legend>
                    <h2 class="text-lg font-semibold text-cocoa">{{ t('cart.delivery_details') }}</h2>
                    <div class="mt-5 grid gap-5">
                        <StoreField id="shipping_city" v-model="form.shipping_city" :label="t('cart.city')" :error="form.errors.shipping_city" autocomplete="address-level2" required />
                        <StoreField id="shipping_address" v-model="form.shipping_address" :label="t('cart.address')" :error="form.errors.shipping_address" autocomplete="street-address" required />
                        <StoreField id="customer_note" v-model="form.customer_note" :label="t('cart.note')" :error="form.errors.customer_note" textarea />
                    </div>
                </fieldset>

                <!-- Honeypot (spatie/laravel-honeypot): hidden from humans, filled by bots. -->
                <div v-if="honeypot?.enabled" class="hidden" aria-hidden="true">
                    <input v-model="(form as Record<string, any>)[honeypot.nameFieldName]" type="text" tabindex="-1" autocomplete="off" />
                </div>
            </div>

            <aside class="lg:col-span-5">
                <OrderSummary :cart="cart" class="lg:sticky lg:top-32">
                    <ul class="mt-5 space-y-2 border-t border-stone-200 pt-4 text-sm">
                        <li v-for="item in cart.items" :key="item.id" class="flex justify-between gap-3">
                            <span class="line-clamp-1 text-stone-600">{{ item.quantity }} × {{ item.name }}</span>
                            <span class="shrink-0" dir="ltr">{{ item.line_total }}</span>
                        </li>
                    </ul>
                    <button type="submit" class="btn-accent mt-6 h-12 w-full text-base" :disabled="form.processing">{{ t('cart.place_order') }}</button>
                    <p class="mt-3 text-center text-xs text-stone-500">{{ t('cart.payment_note') }}</p>
                </OrderSummary>
            </aside>
        </form>
    </div>
</template>
