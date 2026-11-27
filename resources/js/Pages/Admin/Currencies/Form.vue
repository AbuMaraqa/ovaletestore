<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/Components/Forms/FormField.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useCan } from '@/Composables/useCan';
import FormPage from '@/Components/Forms/FormPage.vue';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps<{
    currency: { id: number; code: string; name: Record<string, string>; value: number; is_default: boolean; is_active: boolean; sort_order: number } | null;
    base: string;
    locales: string[];
}>();
const { t } = useTrans();
const { can } = useCan();

const form = useForm({
    code: props.currency?.code ?? '',
    name: Object.fromEntries(props.locales.map((l) => [l, props.currency?.name?.[l] ?? ''])) as Record<string, string>,
    value: (props.currency?.value ?? '') as number | string,
    is_active: props.currency?.is_active ?? false, // new currencies go live only after the rate is checked
    sort_order: props.currency?.sort_order ?? 0,
});
const readOnly = !can(props.currency ? 'currencies.update' : 'currencies.create');
const isBase = computed(() => props.currency?.is_default ?? false);
type VisitHooks = { preserveScroll: boolean; onError: () => void; onSuccess: () => void };
const submit = (hooks: VisitHooks) => (props.currency ? form.put(route('admin.currencies.update', props.currency.id), hooks) : form.post(route('admin.currencies.store'), hooks));
const errors = form.errors as Record<string, string>;

/** Live check of the rate both ways: 1 unit in the base currency, and what a 100 base price becomes. */
const preview = computed(() => {
    const value = Number(form.value);
    const code = form.code.toUpperCase();
    if (!(value > 0) || !/^[A-Z]{3}$/.test(code)) return null;
    try {
        const fmt = (amount: number, c: string) => new Intl.NumberFormat('en', { style: 'currency', currency: c }).format(amount);
        return `${fmt(1, code)} = ${fmt(value, props.base)} · ${fmt(100, props.base)} = ${fmt(100 / value, code)}`;
    } catch {
        return null;
    }
});
</script>

<template>
    <FormPage id="currency-form" :form="form" :action="submit" :title="currency ? `${currency.name.ar ?? currency.code} (${currency.code})` : t('currencies.new')" :back-href="route('admin.currencies.index')" :readonly="readOnly">
        <template #description>{{ t('currencies.help') }}</template>

    <div class="grid gap-6 xl:grid-cols-12">
        <KtCard class="xl:col-span-8">
            <div class="space-y-5">
                <div class="grid gap-5 md:grid-cols-3">
                    <FormField v-for="l in locales" :id="`name-${l}`" :key="l" :label="`${t('admin.name')} (${l})`" :error-key="`name.${l}`" :required="l === 'ar'">
                        <input :id="`name-${l}`" v-model="form.name[l]" class="kt-input" maxlength="60" :dir="l === 'en' ? 'ltr' : 'rtl'" :disabled="readOnly" />
                    </FormField>
                </div>
                <div class="grid gap-5 md:grid-cols-3">
                    <FormField id="code" :label="t('currencies.code')" :help="t('currencies.code_help')" error-key="code" required>
                        <input id="code" v-model="form.code" class="kt-input font-mono uppercase" dir="ltr" maxlength="3" pattern="[A-Za-z]{3}" :disabled="readOnly || isBase" />
                    </FormField>
                    <FormField id="value" class="md:col-span-2" :label="t('currencies.value')" :help="t('currencies.value_help', { base })" error-key="value" required>
                        <div class="kt-input-group" dir="ltr">
                            <span class="kt-input-addon font-mono">1 {{ form.code.toUpperCase() || 'USD' }} =</span>
                            <input id="value" v-model="form.value" type="number" min="0.0001" step="any" class="kt-input font-mono" placeholder="3.70" :disabled="readOnly || isBase" />
                            <span class="kt-input-addon font-mono">{{ base }}</span>
                        </div>
                    </FormField>
                    <FormField id="sort" :label="t('admin.sort_order')" error-key="sort_order">
                        <input id="sort" v-model.number="form.sort_order" type="number" min="0" class="kt-input" dir="ltr" :disabled="readOnly" />
                    </FormField>
                </div>
                <p v-if="preview && !isBase" class="rounded-lg bg-muted/60 px-3 py-2 text-sm tabular-nums text-mono" dir="ltr">{{ preview }}</p>
                <label class="flex items-center gap-3 text-sm"><input v-model="form.is_active" type="checkbox" class="kt-switch" :disabled="readOnly || isBase" /> {{ t('admin.active') }}</label>
                <p v-if="!currency" class="text-xs text-muted-foreground">{{ t('currencies.new_inactive_hint') }}</p>
            </div>
        </KtCard>

        <aside class="space-y-4 xl:col-span-4">
            <div v-if="isBase" class="kt-card gap-3 border-primary/30 bg-primary/5 p-5 text-sm">
                <p class="flex items-start gap-2 text-mono"><KtIcon name="star" class="mt-0.5 text-primary" />{{ t('currencies.base_locked') }}</p>
            </div>
            <div class="kt-card gap-3 p-5 text-sm">
                <p class="flex items-start gap-2 text-secondary-foreground"><KtIcon name="information-2" class="mt-0.5 text-primary" />{{ t('currencies.default_help') }}</p>
                <p class="text-secondary-foreground">{{ t('currencies.how_it_works') }}</p>
            </div>
        </aside>
    </div>
    </FormPage>
</template>
