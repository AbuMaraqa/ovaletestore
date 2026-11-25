<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import FormField from '@/Components/Forms/FormField.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useCan } from '@/Composables/useCan';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps<{
    tier: { id: number; code: string; name: Record<string, string>; discount_percent: number; is_active: boolean; sort_order: number; traders_count: number; prices_count: number } | null;
    locales: string[];
}>();
const { t } = useTrans();
const { can } = useCan();

const form = useForm({
    code: props.tier?.code ?? '',
    name: Object.fromEntries(props.locales.map((l) => [l, props.tier?.name?.[l] ?? ''])) as Record<string, string>,
    discount_percent: props.tier?.discount_percent ?? 0,
    is_active: props.tier?.is_active ?? true,
    sort_order: props.tier?.sort_order ?? 0,
});
const readOnly = !can(props.tier ? 'price_tiers.update' : 'price_tiers.create');
const submit = () => (props.tier ? form.put(route('admin.price-tiers.update', props.tier.id)) : form.post(route('admin.price-tiers.store')));
const errors = form.errors as Record<string, string>;
</script>

<template>
    <PageHeader :title="tier ? tier.name.ar : t('traders.new_tier')" :description="t('traders.tiers_help')">
        <template #actions><KtButton :href="route('admin.price-tiers.index')" variant="outline">{{ t('common.back') }}</KtButton></template>
    </PageHeader>

    <div class="grid gap-6 xl:grid-cols-12">
        <KtCard class="xl:col-span-8">
            <form class="space-y-5" @submit.prevent="submit">
                <div class="grid gap-5 md:grid-cols-3">
                    <FormField v-for="l in locales" :id="`name-${l}`" :key="l" :label="`${t('admin.name')} (${l})`" :error="errors[`name.${l}`]" :required="l === 'ar'">
                        <input :id="`name-${l}`" v-model="form.name[l]" class="kt-input" maxlength="80" :dir="l === 'en' ? 'ltr' : 'rtl'" :disabled="readOnly" />
                    </FormField>
                </div>
                <div class="grid gap-5 md:grid-cols-3">
                    <FormField id="code" :label="t('admin.code')" :help="t('traders.code_help')" :error="form.errors.code" required>
                        <input id="code" v-model="form.code" class="kt-input font-mono" dir="ltr" maxlength="40" pattern="[a-z0-9_-]+" :disabled="readOnly" />
                    </FormField>
                    <FormField id="discount" :label="t('traders.automatic_discount')" :help="t('traders.automatic_discount_help')" :error="form.errors.discount_percent" required>
                        <div class="kt-input-group">
                            <input id="discount" v-model.number="form.discount_percent" type="number" min="0" max="90" step="0.5" class="kt-input" dir="ltr" :disabled="readOnly" />
                            <span class="kt-input-addon">%</span>
                        </div>
                    </FormField>
                    <FormField id="sort" :label="t('admin.sort_order')" :error="form.errors.sort_order">
                        <input id="sort" v-model.number="form.sort_order" type="number" min="0" class="kt-input" dir="ltr" :disabled="readOnly" />
                    </FormField>
                </div>
                <label class="flex items-center gap-3 text-sm"><input v-model="form.is_active" type="checkbox" class="kt-switch" :disabled="readOnly" /> {{ t('admin.active') }}</label>
                <div v-if="!readOnly" class="flex justify-end"><KtButton type="submit" :loading="form.processing" icon="check">{{ t('common.save') }}</KtButton></div>
            </form>
        </KtCard>

        <aside class="space-y-4 xl:col-span-4">
            <div class="kt-card gap-3 p-5 text-sm">
                <p class="flex items-start gap-2 text-secondary-foreground"><KtIcon name="information-2" class="mt-0.5 text-primary" />{{ t('traders.how_it_works') }}</p>
            </div>
            <div v-if="tier" class="grid grid-cols-2 gap-4">
                <div class="kt-card p-4"><p class="text-2xl font-semibold tabular-nums text-mono">{{ tier.traders_count }}</p><p class="text-xs text-secondary-foreground">{{ t('traders.list') }}</p></div>
                <div class="kt-card p-4"><p class="text-2xl font-semibold tabular-nums text-mono">{{ tier.prices_count }}</p><p class="text-xs text-secondary-foreground">{{ t('traders.exact_prices') }}</p></div>
            </div>
        </aside>
    </div>
</template>
