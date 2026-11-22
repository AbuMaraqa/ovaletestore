<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/Components/Forms/FormField.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';

type Names = Record<'ar' | 'en' | 'he', { name: string }>;
interface Term { id?: number; code?: string; color_hex: string | null; translations: Names }

const props = defineProps<{
    attribute: { id: number; code: string; display_type: string; sort_order: number; translations: Names; terms: Term[] } | null;
    displayTypes: string[];
}>();

const { t } = useTrans();
const blankNames = (): Names => ({ ar: { name: '' }, en: { name: '' }, he: { name: '' } });
const form = useForm({
    code: props.attribute?.code ?? '',
    display_type: props.attribute?.display_type ?? 'button',
    sort_order: props.attribute?.sort_order ?? 0,
    translations: props.attribute?.translations ?? blankNames(),
    terms: (props.attribute?.terms ?? []) as Term[],
});
const errors = computed(() => form.errors as Record<string, string>);
const langs = ['ar', 'en', 'he'] as const;

function submit(): void {
    props.attribute ? form.put(route('admin.attributes.update', props.attribute.id), { preserveScroll: true }) : form.post(route('admin.attributes.store'));
}
</script>

<template>
    <PageHeader :title="attribute ? t('admin.edit_attribute') : t('admin.new_attribute')">
        <template #actions>
            <KtButton :href="route('admin.attributes.index')" variant="ghost">{{ t('common.back') }}</KtButton>
            <KtButton type="submit" form="attribute-form" :loading="form.processing" icon="check">{{ t('common.save') }}</KtButton>
        </template>
    </PageHeader>

    <form id="attribute-form" class="space-y-6" @submit.prevent="submit">
        <KtCard :title="t('admin.general')">
            <div class="grid gap-5 md:grid-cols-3">
                <FormField v-for="l in langs" :id="`name-${l}`" :key="l" :label="`${t('admin.name')} (${l})`" :error="errors[`translations.${l}.name`]" :required="l === 'ar'">
                    <input :id="`name-${l}`" v-model="form.translations[l].name" class="kt-input" :dir="l === 'en' ? 'ltr' : 'rtl'" />
                </FormField>
                <FormField id="code" :label="t('admin.code')" :error="errors.code">
                    <input id="code" v-model="form.code" class="kt-input font-mono" dir="ltr" />
                </FormField>
                <FormField id="display" :label="t('admin.display_type')" :error="errors.display_type">
                    <select id="display" v-model="form.display_type" class="kt-select">
                        <option v-for="d in displayTypes" :key="d" :value="d">{{ d }}</option>
                    </select>
                </FormField>
            </div>
        </KtCard>

        <KtCard :title="t('admin.terms')">
            <template #toolbar>
                <KtButton variant="outline" size="sm" icon="plus" @click="form.terms.push({ color_hex: null, translations: blankNames() })">{{ t('admin.add_term') }}</KtButton>
            </template>
            <div class="space-y-3">
                <div v-for="(term, i) in form.terms" :key="term.id ?? `new-${i}`" class="grid items-end gap-3 rounded-lg border border-border p-3 md:grid-cols-[repeat(3,1fr)_auto_auto]">
                    <FormField v-for="l in langs" :id="`t${i}-${l}`" :key="l" :label="l.toUpperCase()" :error="errors[`terms.${i}.translations.${l}.name`]" :required="l === 'ar'">
                        <input :id="`t${i}-${l}`" v-model="term.translations[l].name" class="kt-input" :dir="l === 'en' ? 'ltr' : 'rtl'" />
                    </FormField>
                    <FormField v-if="form.display_type === 'color'" :id="`c${i}`" :label="t('admin.color')">
                        <input :id="`c${i}`" v-model="term.color_hex" type="color" class="kt-input h-10 w-16 p-1" />
                    </FormField>
                    <span v-else />
                    <button type="button" class="kt-btn kt-btn-icon kt-btn-ghost text-destructive" :aria-label="t('common.delete')" @click="form.terms.splice(i, 1)"><KtIcon name="trash" /></button>
                </div>
            </div>
        </KtCard>
    </form>
</template>
