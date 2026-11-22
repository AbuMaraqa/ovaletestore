<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/Components/Forms/FormField.vue';
import LocaleTabs from '@/Components/Forms/LocaleTabs.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';

type Fields = { name: string; slug: string; description: string };
const props = defineProps<{
    brand: { id: number; sort_order: number; is_active: boolean; is_featured: boolean; logo: string | null; translations: Record<string, Fields> } | null;
}>();

const { t } = useTrans();
const blank = (): Fields => ({ name: '', slug: '', description: '' });
const form = useForm({
    sort_order: props.brand?.sort_order ?? 0,
    is_active: props.brand?.is_active ?? true,
    is_featured: props.brand?.is_featured ?? false,
    logo: null as File | null,
    translations: props.brand?.translations ?? { ar: blank(), en: blank(), he: blank() },
    _method: props.brand ? 'put' : 'post',
});
const errors = computed(() => form.errors as Record<string, string>);

function submit(): void {
    const url = props.brand ? route('admin.brands.update', props.brand.id) : route('admin.brands.store');
    form.post(url, { forceFormData: true, preserveScroll: true });
}
</script>

<template>
    <PageHeader :title="brand ? t('common.edit') : t('admin.new_brand')">
        <template #actions>
            <KtButton :href="route('admin.brands.index')" variant="ghost">{{ t('common.back') }}</KtButton>
            <KtButton type="submit" form="brand-form" :loading="form.processing" icon="check">{{ t('common.save') }}</KtButton>
        </template>
    </PageHeader>

    <form id="brand-form" class="grid gap-6 xl:grid-cols-12" @submit.prevent="submit">
        <KtCard :title="t('admin.content')" class="xl:col-span-8">
            <LocaleTabs :errors="errors">
                <template #default="{ locale }">
                    <div class="grid gap-5">
                        <FormField :id="`n-${locale}`" :label="t('admin.name')" :error="errors[`translations.${locale}.name`]" :required="locale === 'ar'">
                            <input :id="`n-${locale}`" v-model="form.translations[locale].name" class="kt-input" />
                        </FormField>
                        <FormField :id="`s-${locale}`" :label="t('admin.slug')" :help="t('admin.slug_help')" :error="errors[`translations.${locale}.slug`]">
                            <input :id="`s-${locale}`" v-model="form.translations[locale].slug" class="kt-input font-mono text-xs" />
                        </FormField>
                        <FormField :id="`d-${locale}`" :label="t('admin.description')" :error="errors[`translations.${locale}.description`]">
                            <textarea :id="`d-${locale}`" v-model="form.translations[locale].description" class="kt-textarea" rows="4" />
                        </FormField>
                    </div>
                </template>
            </LocaleTabs>
        </KtCard>
        <KtCard :title="t('admin.general')" class="xl:col-span-4">
            <div class="space-y-4">
                <FormField id="sort" :label="t('admin.sort_order')"><input id="sort" v-model.number="form.sort_order" type="number" min="0" class="kt-input" /></FormField>
                <label class="flex items-center justify-between text-sm">{{ t('admin.active') }} <input v-model="form.is_active" type="checkbox" class="kt-switch" /></label>
                <label class="flex items-center justify-between text-sm">{{ t('admin.featured') }} <input v-model="form.is_featured" type="checkbox" class="kt-switch" /></label>
                <FormField id="logo" :label="t('admin.logo')" :error="errors.logo">
                    <img v-if="brand?.logo" :src="brand.logo" alt="" class="mb-2 h-16 rounded-lg bg-white object-contain" />
                    <input id="logo" type="file" accept="image/jpeg,image/png,image/webp,image/avif" class="kt-input" @change="form.logo = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                </FormField>
            </div>
        </KtCard>
    </form>
</template>
