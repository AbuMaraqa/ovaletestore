<script setup lang="ts">
import FormCard from '@/Components/Forms/FormCard.vue';
import FormField from '@/Components/Forms/FormField.vue';
import SegmentedControl from '@/Components/Forms/SegmentedControl.vue';
import SwitchRow from '@/Components/Forms/SwitchRow.vue';
import { useTrans } from '@/Composables/useTrans';

/** Status (draft / published / archived), publish date (only when published) and the "featured" flag. */
const status = defineModel<'draft' | 'published' | 'archived'>('status', { required: true });
const publishedAt = defineModel<string | null>('publishedAt', { required: true });
const featured = defineModel<boolean>('featured', { required: true });
const { t } = useTrans();

const options = [
    { value: 'draft' as const, label: t('admin.status_draft'), dot: 'bg-muted-foreground' },
    { value: 'published' as const, label: t('admin.status_published'), dot: 'bg-success' },
    { value: 'archived' as const, label: t('admin.status_archived'), dot: 'bg-warning' },
];
</script>

<template>
    <FormCard :title="t('forms.product.publishing')" icon="rocket" :errors="['status', 'published_at', 'is_featured']">
        <div class="grid gap-5">
            <FormField id="status" :label="t('common.status')" error-key="status">
                <SegmentedControl id="status" v-model="status" :label="t('common.status')" :options="options" />
            </FormField>
            <FormField
                v-if="status === 'published'"
                id="published_at"
                :label="t('forms.product.publish_date')"
                :help="t('forms.product.publish_date_help')"
                error-key="published_at"
                optional
            >
                <input id="published_at" v-model="publishedAt" type="datetime-local" class="kt-input" />
            </FormField>
            <SwitchRow id="is_featured" v-model="featured" :label="t('admin.featured')" :description="t('forms.product.featured_help')" />
        </div>
    </FormCard>
</template>
