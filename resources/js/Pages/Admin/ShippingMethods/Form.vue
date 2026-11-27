<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/Components/Forms/FormField.vue';
import LocaleTabs from '@/Components/Forms/LocaleTabs.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import FormPage from '@/Components/Forms/FormPage.vue';
import { useTrans } from '@/Composables/useTrans';

type Fields = { name: string; description: string };
type Method = {
    id: number;
    type: 'delivery' | 'pickup';
    cost: string | null;
    free_over: string | null;
    min_order: string | null;
    is_active: boolean;
    sort_order: number;
    translations: Record<string, Fields>;
};
const props = defineProps<{ method: Method | null }>();

const { t } = useTrans();
const blank = (): Fields => ({ name: '', description: '' });
const form = useForm({
    type: props.method?.type ?? 'delivery',
    cost: props.method?.cost ?? '0',
    free_over: props.method?.free_over ?? '',
    min_order: props.method?.min_order ?? '',
    is_active: props.method?.is_active ?? true,
    sort_order: props.method?.sort_order ?? 0,
    translations: props.method?.translations ?? { ar: blank(), en: blank(), he: blank() },
});
const errors = computed(() => form.errors as Record<string, string>);

type VisitHooks = { preserveScroll: boolean; onError: () => void; onSuccess: () => void };
function submit(hooks: VisitHooks): void {
    if (props.method) form.put(route('admin.shipping-methods.update', props.method.id), hooks);
    else form.post(route('admin.shipping-methods.store'), hooks);
}
</script>

<template>

    <FormPage id="shipping-form" :form="form" :action="submit" :title="method ? t('common.edit') : t('admin.new_shipping_method')" :back-href="route('admin.shipping-methods.index')">
        <div class="grid gap-6 xl:grid-cols-12">
        <KtCard :title="t('admin.content')" class="xl:col-span-7">
            <LocaleTabs :errors="errors">
                <template #default="{ locale }">
                    <div class="grid gap-5">
                        <FormField :id="`n-${locale}`" :label="t('admin.name')" :error-key="`translations.${locale}.name`" :required="locale === 'ar'">
                            <input :id="`n-${locale}`" v-model="form.translations[locale].name" class="kt-input" />
                        </FormField>
                        <FormField :id="`d-${locale}`" :label="t('admin.description')" :help="t('admin.shipping_description_help')" :error-key="`translations.${locale}.description`">
                            <input :id="`d-${locale}`" v-model="form.translations[locale].description" class="kt-input" />
                        </FormField>
                    </div>
                </template>
            </LocaleTabs>
        </KtCard>

        <KtCard :title="t('admin.general')" class="xl:col-span-5">
            <div class="space-y-4">
                <FormField id="type" :label="t('admin.shipping_type')" error-key="type">
                    <select id="type" v-model="form.type" class="kt-select">
                        <option value="delivery">{{ t('admin.shipping_type_delivery') }}</option>
                        <option value="pickup">{{ t('admin.shipping_type_pickup') }}</option>
                    </select>
                </FormField>
                <FormField id="cost" :label="t('admin.shipping_cost')" :help="t('admin.shipping_cost_help')" error-key="cost">
                    <input id="cost" v-model="form.cost" type="number" step="0.01" min="0" class="kt-input" dir="ltr" />
                </FormField>
                <FormField id="free_over" :label="t('admin.free_over')" :help="t('admin.free_over_help')" error-key="free_over">
                    <input id="free_over" v-model="form.free_over" type="number" step="0.01" min="0" class="kt-input" dir="ltr" />
                </FormField>
                <FormField id="min_order" :label="t('admin.min_order_for_method')" :help="t('admin.min_order_for_method_help')" error-key="min_order">
                    <input id="min_order" v-model="form.min_order" type="number" step="0.01" min="0" class="kt-input" dir="ltr" />
                </FormField>
                <FormField id="sort" :label="t('admin.sort_order')"><input id="sort" v-model.number="form.sort_order" type="number" min="0" class="kt-input" /></FormField>
                <label class="flex items-center justify-between text-sm">{{ t('admin.active') }} <input v-model="form.is_active" type="checkbox" class="kt-switch" /></label>
            </div>
        </KtCard>
        </div>
    </FormPage>
</template>
