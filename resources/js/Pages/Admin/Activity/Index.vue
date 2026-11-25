<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ActivityTimeline from '@/Components/Activity/ActivityTimeline.vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import DataTable from '@/Components/DataTable/DataTable.vue';
import type { Column, Filter, FilterOption, TablePage, TableState } from '@/Components/DataTable/types';
import KtIcon from '@/Components/UI/KtIcon.vue';
import KtModal from '@/Components/UI/KtModal.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import type { ActivityEntry } from '@/Composables/useActivityPanel';
import { useActivityLabels } from '@/Composables/useActivityLabels';
import { useTrans } from '@/Composables/useTrans';

const props = defineProps<{
    activities: TablePage<ActivityEntry>;
    state: TableState;
    logs: string[];
    events: string[];
    subjects: string[];
    causers: FilterOption[];
}>();

const { t } = useTrans();
const { eventLabel, eventTone, subjectLabel, logLabel } = useActivityLabels();

const columns = computed<Column[]>(() => [
    { key: 'event', label: t('activity.event'), fixed: true },
    { key: 'record', label: t('activity.record') },
    { key: 'causer', label: t('activity.user') },
    { key: 'changes', label: t('activity.changes') },
    { key: 'log', label: t('activity.log'), hidden: true },
    { key: 'ip', label: t('activity.ip'), hidden: true },
    { key: 'created_at', label: t('activity.date'), sortable: true, align: 'end' },
]);

const filters = computed<Filter[]>(() => [
    { key: 'log', label: t('activity.log'), type: 'select', options: props.logs.map((l) => ({ value: l, label: logLabel(l) })) },
    { key: 'event', label: t('activity.event'), type: 'select', options: props.events.map((e) => ({ value: e, label: eventLabel(e) })) },
    { key: 'subject', label: t('activity.record'), type: 'select', options: props.subjects.filter((s) => !s.endsWith('_translation')).map((s) => ({ value: s, label: subjectLabel(s) })) },
    { key: 'causer', label: t('activity.user'), type: 'select', options: props.causers },
    { key: 'date', label: t('activity.date'), type: 'date_range' },
]);

const detail = ref<ActivityEntry | null>(null);
const detailOpen = computed({ get: () => detail.value !== null, set: (v: boolean) => !v && (detail.value = null) });
</script>

<template>
    <PageHeader :title="t('activity.title')" :description="t('activity.subtitle')" />

    <DataTable :page="activities" :state="state" :columns="columns" :filters="filters" :url="route('admin.activity.index')" storage-key="activity" :title="t('activity.title')">
        <template #cell-event="{ row }">
            <div class="flex items-center gap-2.5">
                <span :class="['flex size-8 shrink-0 items-center justify-center rounded-full border', eventTone(row.event).badge]"><KtIcon :name="eventTone(row.event).icon" /></span>
                <span class="font-medium text-mono">{{ eventLabel(row.event) }}</span>
            </div>
        </template>
        <template #cell-record="{ row }">
            <div class="min-w-0 max-w-xs">
                <span v-if="row.subject" class="block text-xs text-muted-foreground">
                    {{ subjectLabel(row.subject) }}<template v-if="row.child"> · {{ subjectLabel(row.child) }}</template><template v-if="row.locale"> ({{ row.locale }})</template>
                </span>
                <Link v-if="row.url" :href="row.url" class="line-clamp-1 font-medium text-mono hover:text-primary">{{ row.label ?? '—' }}</Link>
                <span v-else class="line-clamp-1 font-medium text-mono">{{ row.label ?? '—' }}</span>
            </div>
        </template>
        <template #cell-causer="{ row }">
            <div v-if="row.causer" class="flex items-center gap-2">
                <UserAvatar v-if="row.causer.avatar" :avatar="row.causer.avatar" :name="row.causer.name" :ring="false" size="size-7 text-[10px]" />
                <span class="text-sm">{{ row.causer.name }}</span>
            </div>
            <span v-else class="text-sm text-muted-foreground">{{ t('activity.system') }}</span>
        </template>
        <template #cell-changes="{ row }">
            <button v-if="row.changes.length" type="button" class="kt-badge kt-badge-outline kt-badge-primary" @click="detail = row">
                {{ t('activity.changes_count', { count: row.changes.length }) }}
            </button>
            <span v-else class="text-muted-foreground">—</span>
        </template>
        <template #cell-log="{ row }"><span class="kt-badge kt-badge-sm kt-badge-outline">{{ logLabel(row.log ?? 'default') }}</span></template>
        <template #cell-ip="{ row }"><span class="font-mono text-xs" dir="ltr">{{ row.ip ?? '—' }}</span></template>
        <template #cell-created_at="{ row }">
            <time :datetime="row.created_at" :title="row.date" class="whitespace-nowrap text-sm text-secondary-foreground">{{ row.ago }}</time>
        </template>
        <template #actions="{ row }">
            <button type="button" class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" :title="t('activity.details')" :aria-label="t('activity.details')" @click="detail = row">
                <KtIcon name="eye" />
            </button>
        </template>
    </DataTable>

    <KtModal v-model:open="detailOpen" :title="t('activity.details')" width="max-w-2xl">
        <ActivityTimeline v-if="detail" :entries="[detail]" show-subject />
        <dl v-if="detail?.agent" class="mt-4 border-t border-border pt-3 text-xs text-muted-foreground">
            <dt class="font-medium">{{ t('activity.ip') }}: <span dir="ltr">{{ detail.ip }}</span></dt>
            <dd class="mt-1 break-all" dir="ltr">{{ detail.agent }}</dd>
        </dl>
    </KtModal>
</template>
