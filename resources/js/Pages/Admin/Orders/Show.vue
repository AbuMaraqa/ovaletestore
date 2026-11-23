<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import OrderStatusBadge from '@/Components/Admin/OrderStatusBadge.vue';
import FormField from '@/Components/Forms/FormField.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps<{
    order: {
        id: number; number: string; status: string; allowed_statuses: string[];
        customer_name: string; customer_phone: string; customer_email: string | null;
        shipping_city: string | null; shipping_address: string | null; shipping_method: string | null; customer_note: string | null;
        subtotal: string; shipping_total: string; total: string; placed_at: string;
        items: { id: number; name: string; variant_label: string | null; sku: string | null; quantity: number; unit_price: string; line_total: string }[];
        notes: { id: number; body: string; author: string | null; date: string }[];
    };
}>();

const { t } = useTrans();
const form = useForm({ status: props.order.allowed_statuses[0] ?? '', note: '' });
const submit = () => form.patch(route('admin.orders.status', props.order.id), { preserveScroll: true, onSuccess: () => form.reset('note') });
</script>

<template>
    <PageHeader :title="`${t('orders.number')} ${order.number}`" :description="order.placed_at">
        <template #actions>
            <OrderStatusBadge :status="order.status" />
            <KtButton :href="route('admin.orders.index')" variant="ghost">{{ t('common.back') }}</KtButton>
        </template>
    </PageHeader>

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
                                    <p class="text-xs text-muted-foreground">{{ item.variant_label }} <span v-if="item.sku" dir="ltr">· {{ item.sku }}</span></p>
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
                    </dl>
                </template>
            </KtCard>

            <KtCard :title="t('orders.notes')">
                <p v-if="!order.notes.length" class="text-sm text-muted-foreground">{{ t('common.no_results') }}</p>
                <ul class="space-y-3">
                    <li v-for="note in order.notes" :key="note.id" class="rounded-lg bg-muted/50 p-3 text-sm">
                        <p class="whitespace-pre-line">{{ note.body }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ note.author }} · {{ note.date }}</p>
                    </li>
                </ul>
            </KtCard>
        </div>

        <aside class="space-y-6 xl:col-span-4">
            <KtCard :title="t('admin.customer')">
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-muted-foreground">{{ t('admin.name') }}</dt><dd class="font-medium">{{ order.customer_name }}</dd></div>
                    <div><dt class="text-muted-foreground">{{ t('admin.phone') }}</dt><dd><a :href="`tel:${order.customer_phone}`" class="text-primary" dir="ltr">{{ order.customer_phone }}</a></dd></div>
                    <div v-if="order.customer_email"><dt class="text-muted-foreground">Email</dt><dd dir="ltr">{{ order.customer_email }}</dd></div>
                    <div><dt class="text-muted-foreground">{{ t('admin.shipping_method') }}</dt><dd class="font-medium">{{ order.shipping_method ?? '—' }}</dd></div>
                    <div v-if="order.shipping_city || order.shipping_address"><dt class="text-muted-foreground">{{ t('orders.delivery') }}</dt><dd>{{ [order.shipping_city, order.shipping_address].filter(Boolean).join(' — ') }}</dd></div>
                    <div v-if="order.customer_note"><dt class="text-muted-foreground">{{ t('orders.customer_note') }}</dt><dd class="whitespace-pre-line">{{ order.customer_note }}</dd></div>
                </dl>
            </KtCard>

            <KtCard v-if="order.allowed_statuses.length" :title="t('orders.change_status')">
                <form class="space-y-4" @submit.prevent="submit">
                    <FormField id="status" :label="t('common.status')" :error="form.errors.status">
                        <select id="status" v-model="form.status" class="kt-select">
                            <option v-for="s in order.allowed_statuses" :key="s" :value="s">{{ t(`orders.status_${s}`) }}</option>
                        </select>
                    </FormField>
                    <FormField id="note" :label="t('orders.note')" :error="form.errors.note">
                        <textarea id="note" v-model="form.note" class="kt-textarea" rows="3" />
                    </FormField>
                    <KtButton type="submit" :loading="form.processing" class="w-full">{{ t('common.save') }}</KtButton>
                </form>
            </KtCard>
        </aside>
    </div>
</template>
