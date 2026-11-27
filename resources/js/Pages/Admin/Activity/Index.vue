<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ActivityTimeline from '@/Components/Activity/ActivityTimeline.vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import type { TablePage, TableState } from '@/Components/DataTable/types';
import ResourceTable from '@/Components/Table/ResourceTable.vue';
import type { TableDef } from '@/Components/Table/types';
import KtIcon from '@/Components/UI/KtIcon.vue';
import KtModal from '@/Components/UI/KtModal.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import type { ActivityEntry } from '@/Composables/useActivityPanel';
import { useActivityLabels } from '@/Composables/useActivityLabels';
import { useTrans } from '@/Composables/useTrans';

/**
 * The audit trail (App\Tables\Admin\ActivityTable): every channel the viewer may read, incl. sign-ins and
 * sign-outs. Read-only; export of the filtered entries. Rich cells stay here (icons, avatar, changes modal).
 */
defineProps<{ activities: TablePage<ActivityEntry>; state: TableState; table: TableDef }>();

const { t } = useTrans();
const { eventLabel, eventTone, subjectLabel, logLabel } = useActivityLabels();

const detail = ref<ActivityEntry | null>(null);
const detailOpen = computed({ get: () => detail.value !== null, set: (v: boolean) => !v && (detail.value = null) });
</script>

<template>
    <PageHeader :title="t('activity.title')" :description="t('activity.subtitle')" />

    <ResourceTable :table="table" :page="activities" :state="state">
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
        <template #cell-created_at="{ row }">
            <time :datetime="row.created_at" :title="row.date" class="whitespace-nowrap text-sm text-secondary-foreground">{{ row.ago }}</time>
        </template>
        <template #row-actions="{ row }">
            <button type="button" class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" :title="t('activity.details')" :aria-label="t('activity.details')" @click="detail = row">
                <KtIcon name="eye" />
            </button>
        </template>
    </ResourceTable>

    <KtModal v-model:open="detailOpen" :title="t('activity.details')" width="max-w-2xl">
        <ActivityTimeline v-if="detail" :entries="[detail]" show-subject />
        <dl v-if="detail?.agent" class="mt-4 border-t border-border pt-3 text-xs text-muted-foreground">
            <dt class="font-medium">{{ t('activity.ip') }}: <span dir="ltr">{{ detail.ip }}</span></dt>
            <dd class="mt-1 break-all" dir="ltr">{{ detail.agent }}</dd>
        </dl>
    </KtModal>
</template>
