<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormField from '@/Components/Forms/FormField.vue';
import LocaleTabs from '@/Components/Forms/LocaleTabs.vue';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { useTrans } from '@/Composables/useTrans';

type Fields = { name: string; slug: string; description: string; meta_title: string; meta_description: string };
const props = defineProps<{
    category: { id: number; parent_id: number | null; sort_order: number; is_active: boolean; image: string | null; translations: Record<string, Fields> } | null;
    parents: { id: number; name: string }[];
}>();

const { t } = useTrans();
const blank = (): Fields => ({ name: '', slug: '', description: '', meta_title: '', meta_description: '' });
const form = useForm({
    parent_id: props.category?.parent_id ?? null,
    sort_order: props.category?.sort_order ?? 0,
    is_active: props.category?.is_active ?? true,
    image: null as File | null,
    translations: props.category?.translations ?? { ar: blank(), en: blank(), he: blank() },
    _method: props.category ? 'put' : 'post',
});
const errors = computed(() => form.errors as Record<string, string>);

function submit(): void {
    // POST + _method so file uploads work for updates too.
    const url = props.category ? route('admin.categories.update', props.category.id) : route('admin.categories.store');
    form.post(url, { forceFormData: true, preserveScroll: true });
}
</script>

<template>
    <PageHeader :title="category ? t('admin.edit_category') : t('admin.new_category')">
        <template #actions>
            <KtButton :href="route('admin.categories.index')" variant="ghost">{{ t('common.back') }}</KtButton>
            <KtButton type="submit" form="category-form" :loading="form.processing" icon="check">{{ t('common.save') }}</KtButton>
        </template>
    </PageHeader>

    <form id="category-form" class="grid gap-6 xl:grid-cols-12" @submit.prevent="submit">
        <KtCard :title="t('admin.content')" :description="t('admin.translations_help')" class="xl:col-span-8">
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
                        <div class="grid gap-5 md:grid-cols-2">
                            <FormField :id="`mt-${locale}`" :label="t('admin.meta_title')" :error="errors[`translations.${locale}.meta_title`]">
                                <input :id="`mt-${locale}`" v-model="form.translations[locale].meta_title" class="kt-input" maxlength="70" />
                            </FormField>
                            <FormField :id="`md-${locale}`" :label="t('admin.meta_description')" :error="errors[`translations.${locale}.meta_description`]">
                                <input :id="`md-${locale}`" v-model="form.translations[locale].meta_description" class="kt-input" maxlength="170" />
                            </FormField>
                        </div>
                    </div>
                </template>
            </LocaleTabs>
        </KtCard>

        <KtCard :title="t('admin.general')" class="xl:col-span-4">
            <div class="space-y-4">
                <FormField id="parent" :label="t('admin.parent_category')" :error="errors.parent_id">
                    <select id="parent" v-model="form.parent_id" class="kt-select">
                        <option :value="null">{{ t('admin.no_parent') }}</option>
                        <option v-for="p in parents" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </FormField>
                <FormField id="sort" :label="t('admin.sort_order')" :error="errors.sort_order">
                    <input id="sort" v-model.number="form.sort_order" type="number" min="0" class="kt-input" />
                </FormField>
                <label class="flex items-center justify-between text-sm">{{ t('admin.active') }} <input v-model="form.is_active" type="checkbox" class="kt-switch" /></label>
                <FormField id="image" :label="t('admin.images')" :error="errors.image">
                    <img v-if="category?.image" :src="category.image" alt="" class="mb-2 size-24 rounded-lg object-cover" />
                    <input id="image" type="file" accept="image/jpeg,image/png,image/webp,image/avif" class="kt-input" @change="form.image = ($event.target as HTMLInputElement).files?.[0] ?? null" />
                </FormField>
            </div>
        </KtCard>
    </form>
</template>
