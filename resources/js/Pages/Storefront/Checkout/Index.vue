<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import OrderSummary from '@/Components/Storefront/OrderSummary.vue';
import StoreField from '@/Components/Storefront/StoreField.vue';
import { useTrans } from '@/Composables/useTrans';
import type { CartPayload } from '@/types/cart';

type ShippingOption = { id: number; name: string; description: string | null; requires_address: boolean; cost: string | null; total: string; free_over: string | null };

const props = defineProps<{
    cart: CartPayload;
    shipping_options: ShippingOption[];
    prefill: { customer_name: string; customer_email: string | null; customer_phone: string | null } | null;
}>();

const { t } = useTrans();
const honeypot = usePage().props.honeypot as { nameFieldName: string; validFromFieldName: string; encryptedValidFrom: string; enabled: boolean } | undefined;

const form = useForm({
    customer_name: props.prefill?.customer_name ?? '',
    customer_phone: props.prefill?.customer_phone ?? '',
    customer_email: props.prefill?.customer_email ?? '',
    shipping_method_id: props.shipping_options[0]?.id ?? null,
    shipping_city: '',
    shipping_address: '',
    customer_note: '',
    ...(honeypot?.enabled ? { [honeypot.nameFieldName]: '', [honeypot.validFromFieldName]: honeypot.encryptedValidFrom } : {}),
});

const selected = computed(() => props.shipping_options.find((o) => o.id === form.shipping_method_id) ?? null);
const needsAddress = computed(() => selected.value?.requires_address ?? true);
// Summary reflects the chosen method (costs are computed server-side per option).
const summary = computed<CartPayload>(() =>
    selected.value ? { ...props.cart, shipping_total: selected.value.cost, total: selected.value.total } : props.cart,
);

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
                    <legend class="text-lg font-semibold text-cocoa">{{ t('cart.shipping_method') }}</legend>
                    <div class="mt-5 grid gap-3" role="radiogroup">
                        <label
                            v-for="option in shipping_options"
                            :key="option.id"
                            class="flex cursor-pointer items-start gap-4 rounded-2xl p-4 ring-1 transition"
                            :class="form.shipping_method_id === option.id ? 'bg-brand-50/60 ring-2 ring-brand-500' : 'ring-stone-200 hover:ring-stone-300'"
                        >
                            <input v-model="form.shipping_method_id" type="radio" name="shipping_method_id" :value="option.id" class="mt-1 size-4 accent-brand-600" />
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-cocoa">{{ option.name }}</span>
                                <span v-if="option.description" class="mt-0.5 block text-sm text-stone-500">{{ option.description }}</span>
                                <span v-if="option.free_over" class="mt-1 block text-xs font-medium text-brand-700">{{ t('cart.free_over_hint', { amount: option.free_over }) }}</span>
                            </span>
                            <span class="shrink-0 text-sm font-bold" :class="option.cost ? 'text-cocoa' : 'text-emerald-700'" dir="ltr">{{ option.cost ?? t('cart.free') }}</span>
                        </label>
                    </div>
                    <p v-if="form.errors.shipping_method_id" class="mt-3 text-sm text-red-600" role="alert">{{ form.errors.shipping_method_id }}</p>
                </fieldset>

                <fieldset class="rounded-[2rem] bg-white p-6 ring-1 ring-stone-100 sm:p-8">
                    <legend class="sr-only">{{ t('cart.delivery_details') }}</legend>
                    <h2 class="text-lg font-semibold text-cocoa">{{ t('cart.delivery_details') }}</h2>
                    <div class="mt-5 grid gap-5">
                        <template v-if="needsAddress">
                            <StoreField id="shipping_city" v-model="form.shipping_city" :label="t('cart.city')" :error="form.errors.shipping_city" autocomplete="address-level2" required />
                            <StoreField id="shipping_address" v-model="form.shipping_address" :label="t('cart.address')" :error="form.errors.shipping_address" autocomplete="street-address" required />
                        </template>
                        <p v-else class="rounded-2xl bg-stone-50 px-4 py-3 text-sm text-stone-600">{{ t('cart.pickup_note') }}</p>
                        <StoreField id="customer_note" v-model="form.customer_note" :label="t('cart.note')" :error="form.errors.customer_note" textarea />
                    </div>
                </fieldset>

                <!-- Honeypot (spatie/laravel-honeypot): hidden from humans, filled by bots. -->
                <div v-if="honeypot?.enabled" class="hidden" aria-hidden="true">
                    <input v-model="(form as Record<string, any>)[honeypot.nameFieldName]" type="text" tabindex="-1" autocomplete="off" />
                </div>
            </div>

            <aside class="lg:col-span-5">
                <OrderSummary :cart="summary" class="lg:sticky lg:top-32">
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
