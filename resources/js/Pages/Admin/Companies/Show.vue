<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import DeleteButton from '@/Components/Admin/DeleteButton.vue';
import OrderStatusBadge from '@/Components/Admin/OrderStatusBadge.vue';
import FormField from '@/Components/Forms/FormField.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';

type Entry = {
    type: 'order' | 'payment';
    id: number;
    date: string;
    reference: string | null;
    detail: string | null;
    note?: string | null;
    status: string | null;
    debit: string | null;
    credit: string | null;
    balance: string;
};
const props = defineProps<{
    company: { id: number; name: string; contact_name: string | null; phone: string | null; city: string | null; address: string | null; notes: string | null; is_active: boolean; balance: string; has_debt: boolean };
    statement: Entry[];
    payment_methods: string[];
    today: string;
}>();
const { t } = useTrans();

const payment = useForm({ amount: '', method: 'cash', reference: '', note: '', paid_at: props.today });
function record(): void {
    payment.post(route('admin.companies.payments.store', props.company.id), { preserveScroll: true, onSuccess: () => payment.reset('amount', 'reference', 'note') });
}
</script>

<template>
    <PageHeader :title="company.name" :description="[company.city, company.contact_name, company.phone].filter(Boolean).join(' · ')">
        <template #actions>
            <KtButton :href="route('admin.companies.index')" variant="ghost">{{ t('common.back') }}</KtButton>
            <KtButton :href="route('admin.orders.index', { filter: { company_id: company.id } })" variant="ghost">{{ t('admin.view_orders') }}</KtButton>
            <a :href="route('admin.companies.statement', company.id)" target="_blank" rel="noopener" class="kt-btn kt-btn-outline"><KtIcon name="printer" />{{ t('invoices.print_statement') }}</a>
            <KtButton :href="route('admin.companies.edit', company.id)" icon="pencil">{{ t('common.edit') }}</KtButton>
        </template>
    </PageHeader>

    <div class="grid gap-6 xl:grid-cols-12">
        <div class="space-y-6 xl:col-span-4">
            <KtCard>
                <p class="text-sm text-muted-foreground">{{ t('admin.balance') }}</p>
                <p class="mt-1 text-3xl font-bold tabular-nums" :class="company.has_debt ? 'text-destructive' : 'text-mono'" dir="ltr">{{ company.balance }}</p>
                <p v-if="!company.is_active" class="mt-3 text-sm text-muted-foreground">{{ t('admin.inactive') }}</p>
                <p v-if="company.address" class="mt-3 text-sm">{{ company.address }}</p>
                <p v-if="company.notes" class="mt-3 whitespace-pre-line text-sm text-muted-foreground">{{ company.notes }}</p>
            </KtCard>

            <KtCard :title="t('admin.record_payment')">
                <form class="grid gap-4" @submit.prevent="record">
                    <FormField id="amount" :label="t('admin.amount')" :error="payment.errors.amount" required>
                        <input id="amount" v-model="payment.amount" type="number" step="0.01" min="0.01" class="kt-input" dir="ltr" />
                    </FormField>
                    <FormField id="method" :label="t('admin.payment_method')" :error="payment.errors.method">
                        <select id="method" v-model="payment.method" class="kt-select">
                            <option v-for="m in payment_methods" :key="m" :value="m">{{ t(`admin.payment_${m}`) }}</option>
                        </select>
                    </FormField>
                    <FormField id="paid_at" :label="t('admin.paid_at')" :error="payment.errors.paid_at">
                        <input id="paid_at" v-model="payment.paid_at" type="date" :max="today" class="kt-input" dir="ltr" />
                    </FormField>
                    <FormField id="reference" :label="t('admin.reference')" :error="payment.errors.reference">
                        <input id="reference" v-model="payment.reference" class="kt-input" dir="ltr" />
                    </FormField>
                    <FormField id="pnote" :label="t('admin.notes')" :error="payment.errors.note">
                        <input id="pnote" v-model="payment.note" class="kt-input" />
                    </FormField>
                    <KtButton type="submit" :loading="payment.processing" icon="check">{{ t('admin.record_payment') }}</KtButton>
                </form>
            </KtCard>
        </div>

        <KtCard :title="t('admin.statement')" flush class="xl:col-span-8">
            <div class="kt-table-wrapper overflow-x-auto">
                <table class="kt-table">
                    <thead>
                        <tr>
                            <th>{{ t('admin.date') }}</th>
                            <th>{{ t('admin.reference') }}</th>
                            <th class="text-end">{{ t('admin.debit') }}</th>
                            <th class="text-end">{{ t('admin.credit') }}</th>
                            <th class="text-end">{{ t('admin.balance') }}</th>
                            <th class="w-10" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in statement" :key="`${e.type}-${e.id}`">
                            <td class="text-sm" dir="ltr">{{ e.date }}</td>
                            <td>
                                <template v-if="e.type === 'order'">
                                    <Link :href="route('admin.orders.show', e.id)" class="font-mono text-sm text-primary" dir="ltr">{{ e.reference }}</Link>
                                    <OrderStatusBadge v-if="e.status" :status="e.status" class="ms-2" />
                                    <span v-if="e.detail" class="block text-xs text-muted-foreground">{{ t('admin.sales_rep') }}: {{ e.detail }}</span>
                                </template>
                                <template v-else>
                                    <span class="font-medium">{{ t('admin.entry_payment') }} — {{ t(`admin.payment_${e.detail}`) }}</span>
                                    <span v-if="e.reference" class="ms-1 text-sm" dir="ltr">#{{ e.reference }}</span>
                                    <span v-if="e.note" class="block text-xs text-muted-foreground">{{ e.note }}</span>
                                </template>
                            </td>
                            <td class="text-end tabular-nums" dir="ltr">{{ e.debit ?? '' }}</td>
                            <td class="text-end tabular-nums text-success" dir="ltr">{{ e.credit ?? '' }}</td>
                            <td class="text-end font-semibold tabular-nums" dir="ltr">{{ e.balance }}</td>
                            <td><DeleteButton v-if="e.type === 'payment'" :href="route('admin.companies.payments.destroy', [company.id, e.id])" /></td>
                        </tr>
                        <tr v-if="!statement.length"><td colspan="6" class="py-12 text-center text-muted-foreground">{{ t('common.no_results') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </KtCard>
    </div>
</template>
