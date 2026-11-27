<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import OrderStatusFlow from '@/Components/Admin/Orders/OrderStatusFlow.vue';
import OrderStatusBadge from '@/Components/Admin/OrderStatusBadge.vue';
import FormField from '@/Components/Forms/FormField.vue';
import RowActions from '@/Components/DataTable/RowActions.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps<{
    order: {
        id: number; number: string; status: string; allowed_statuses: string[]; status_flow: string[]; customer_account: { name: string; url: string } | null; delete_url: string | null;
        customer_name: string; customer_phone: string; customer_email: string | null;
        shipping_city: string | null; shipping_address: string | null; shipping_method: string | null;
        channel: 'web' | 'pos'; payment_method: 'cod' | 'on_account'; company_id: number | null; sales_rep: string | null; customer_note: string | null;
        subtotal: string; shipping_total: string; total: string; placed_at: string;
        foreign_currency: { note: string; base_total: string } | null;
        invoice: { number: string; url: string } | null;
        label_url: string;
        sales_order_url: string | null;
        items: { id: number; name: string; variant_label: string | null; sku: string | null; quantity: number; unit_price: string; line_total: string }[];
        notes: { id: number; body: string; author: string | null; date: string }[];
    };
}>();

const { t } = useTrans();
const { confirm } = useConfirm();
const form = useForm({ status: props.order.allowed_statuses[0] ?? '', note: '' });
// After a change the order has new next steps: pre-select the first of them again.
watch(() => props.order.allowed_statuses, (next) => {
    form.status = next[0] ?? '';
    form.defaults('status', form.status);
});

const skips = computed(() => props.order.status_flow.indexOf(form.status) - props.order.status_flow.indexOf(props.order.status) > 1);
const submitLabel = computed(() => (form.status === 'cancelled' ? t('orders.cancel_order') : t('orders.move_to_status', { status: t(`orders.status_${form.status}`) })));

/** Cancelling restocks and skipping steps is unusual: both ask first. */
async function submit(): Promise<void> {
    if (form.status === 'cancelled' || skips.value) {
        const ok = await confirm({
            title: submitLabel.value,
            message: form.status === 'cancelled' ? t('orders.confirm_cancel') : t('orders.confirm_skip'),
            confirmLabel: submitLabel.value,
            danger: form.status === 'cancelled',
        });
        if (!ok) return;
    }
    form.patch(route('admin.orders.status', props.order.id), { preserveScroll: true, onSuccess: () => form.reset('note') });
}
</script>

<template>
    <PageHeader :title="`${t('orders.number')} ${order.number}`" :description="order.placed_at">
        <template #actions>
            <OrderStatusBadge :status="order.status" />
            <!-- PDFs open in a new tab (not Inertia visits). -->
            <a v-if="order.invoice" :href="order.invoice.url" target="_blank" rel="noopener" class="kt-btn kt-btn-outline" :title="order.invoice.number">
                <KtIcon name="document" />{{ t('invoices.view') }}
            </a>
            <a v-if="order.sales_order_url" :href="order.sales_order_url" target="_blank" rel="noopener" class="kt-btn kt-btn-outline">
                <KtIcon name="note-2" />{{ t('invoices.sales_order') }}
            </a>
            <a :href="order.label_url" target="_blank" rel="noopener" class="kt-btn kt-btn-outline">
                <KtIcon name="delivery" />{{ t('invoices.print_label') }}
            </a>
            <KtButton :href="route('admin.orders.index')" variant="outline">{{ t('common.back') }}</KtButton>
            <RowActions :delete-url="order.delete_url" :label="order.number" :activity="{ type: 'order', id: order.id }" />
        </template>
    </PageHeader>

    <KtCard class="mb-6">
        <OrderStatusFlow :status="order.status" :flow="order.status_flow" />
    </KtCard>

    <div class="grid gap-6 xl:grid-cols-12">
        <div class="space-y-6 xl:col-span-8">
            <KtCard :title="t('admin.items')" flush>
                <div class="kt-table-wrapper overflow-x-auto">
                    <table class="kt-table">
                        <thead><tr><th>{{ t('admin.product') }}</th><th class="text-center">×</th><th class="text-end">{{ t('admin.price') }}</th><th class="text-end">{{ t('admin.total') }}</th></tr></thead>
                        <tbody>
                            <tr v-for="item in order.items" :key="item.id">
                                <td>
                                    <p class="font-medium text-mono">{{ item.name }}</p>
                                    <p v-if="item.variant_label || item.sku" class="text-xs text-secondary-foreground">{{ [item.variant_label, item.sku].filter(Boolean).join(' · ') }}</p>
                                </td>
                                <td class="text-center tabular-nums">{{ item.quantity }}</td>
                                <td class="text-end" dir="ltr">{{ item.unit_price }}</td>
                                <td class="text-end font-medium" dir="ltr">{{ item.line_total }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <template #footer>
                    <dl class="ms-auto w-64 space-y-1 text-sm">
                        <div class="flex justify-between"><dt class="text-muted-foreground">{{ t('admin.price') }}</dt><dd dir="ltr">{{ order.subtotal }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted-foreground">{{ t('admin.shipping') }}</dt><dd dir="ltr">{{ order.shipping_total }}</dd></div>
                        <div class="flex justify-between font-semibold text-mono"><dt>{{ t('admin.total') }}</dt><dd dir="ltr">{{ order.total }}</dd></div>
                        <template v-if="order.foreign_currency">
                            <div class="flex justify-between border-t border-dashed border-border pt-2 text-xs text-muted-foreground"><dt>{{ t('currencies.base_equivalent') }}</dt><dd dir="ltr">{{ order.foreign_currency.base_total }}</dd></div>
                            <p class="text-xs text-muted-foreground">{{ order.foreign_currency.note }}</p>
                        </template>
                    </dl>
                </template>
            </KtCard>

            <KtCard :title="t('orders.notes')">
                <p v-if="!order.notes.length" class="text-sm text-muted-foreground">{{ t('common.no_results') }}</p>
                <ul class="space-y-3">
                    <li v-for="note in order.notes" :key="note.id" class="rounded-lg bg-muted/50 p-3 text-sm">
                        <p class="whitespace-pre-line">{{ note.body }}</p>
                        <p class="mt-1 text-xs text-secondary-foreground">{{ [note.author, note.date].filter(Boolean).join(' · ') }}</p>
                    </li>
                </ul>
            </KtCard>
        </div>

        <aside class="space-y-6 xl:col-span-4">
            <KtCard :title="t('admin.customer')">
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-muted-foreground">{{ t('admin.name') }}</dt><dd class="font-medium">{{ order.customer_name }}</dd></div>
                    <div>
                        <dt class="text-muted-foreground">{{ t('customers.account') }}</dt>
                        <dd>
                            <Link v-if="order.customer_account" :href="order.customer_account.url" class="inline-flex items-center gap-1.5 font-medium text-primary hover:underline"><KtIcon name="profile-circle" />{{ t('customers.registered_account') }}</Link>
                            <span v-else class="text-secondary-foreground">{{ t('customers.guest') }}</span>
                        </dd>
                    </div>
                    <div><dt class="text-muted-foreground">{{ t('admin.phone') }}</dt><dd><a :href="`tel:${order.customer_phone}`" class="text-primary"><bdi dir="ltr">{{ order.customer_phone }}</bdi></a></dd></div>
                    <div v-if="order.customer_email"><dt class="text-muted-foreground">{{ t('common.email') }}</dt><dd><a :href="`mailto:${order.customer_email}`" class="text-primary"><bdi dir="ltr">{{ order.customer_email }}</bdi></a></dd></div>
                    <div><dt class="text-muted-foreground">{{ t('admin.channel') }}</dt><dd class="font-medium">{{ t(`admin.channel_${order.channel}`) }}<span v-if="order.sales_rep"> — {{ order.sales_rep }}</span></dd></div>
                    <div><dt class="text-muted-foreground">{{ t('admin.payment_method') }}</dt><dd class="font-medium">{{ t(`admin.pay_${order.payment_method}`) }}</dd></div>
                    <div v-if="order.company_id"><dt class="text-muted-foreground">{{ t('admin.company') }}</dt><dd><Link :href="route('admin.companies.show', order.company_id)" class="font-medium text-primary">{{ order.customer_name }}</Link></dd></div>
                    <div><dt class="text-muted-foreground">{{ t('admin.shipping_method') }}</dt><dd class="font-medium">{{ order.shipping_method ?? '—' }}</dd></div>
                    <div v-if="order.shipping_city || order.shipping_address"><dt class="text-muted-foreground">{{ t('orders.delivery') }}</dt><dd>{{ [order.shipping_city, order.shipping_address].filter(Boolean).join(' — ') }}</dd></div>
                    <div v-if="order.customer_note"><dt class="text-muted-foreground">{{ t('orders.customer_note') }}</dt><dd class="whitespace-pre-line">{{ order.customer_note }}</dd></div>
                </dl>
            </KtCard>

            <KtCard v-if="order.allowed_statuses.length" :title="t('orders.change_status')">
                <form class="space-y-4" @submit.prevent="submit">
                    <fieldset>
                        <legend class="mb-2 text-sm font-medium text-mono">{{ t('orders.move_to') }}</legend>
                        <div class="grid gap-2">
                            <label
                                v-for="s in order.allowed_statuses"
                                :key="s"
                                :class="[
                                    'flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-2.5 text-sm transition',
                                    form.status === s ? (s === 'cancelled' ? 'border-destructive bg-destructive/5' : 'border-primary bg-primary/5') : 'border-border hover:bg-accent/60',
                                ]"
                            >
                                <input v-model="form.status" type="radio" name="status" :value="s" class="kt-radio kt-radio-sm" />
                                <span :class="['font-medium', s === 'cancelled' ? 'text-destructive' : 'text-mono']">{{ t(`orders.status_${s}`) }}</span>
                                <span v-if="s === 'cancelled'" class="ms-auto text-xs text-muted-foreground">{{ t('orders.cancel_restocks') }}</span>
                            </label>
                        </div>
                        <p v-if="form.errors.status" class="mt-1 text-sm text-destructive">{{ form.errors.status }}</p>
                    </fieldset>
                    <FormField id="note" :label="t('orders.note')" :error="form.errors.note">
                        <textarea id="note" v-model="form.note" class="kt-textarea" rows="3" />
                    </FormField>
                    <KtButton type="submit" :loading="form.processing" :disabled="!form.status" :variant="form.status === 'cancelled' ? 'destructive' : 'primary'" class="w-full">{{ submitLabel }}</KtButton>
                </form>
            </KtCard>
        </aside>
    </div>
</template>
