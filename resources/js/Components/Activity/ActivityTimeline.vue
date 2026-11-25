<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import type { ActivityEntry } from '@/Composables/useActivityPanel';
import { useActivityLabels } from '@/Composables/useActivityLabels';
import { useTrans } from '@/Composables/useTrans';

/** Metronic-style timeline of audit entries with their old → new values. */
defineProps<{ entries: ActivityEntry[]; showSubject?: boolean }>();
const { t } = useTrans();
const { eventLabel, eventTone, subjectLabel, fieldLabel, value } = useActivityLabels();
</script>

<template>
    <ol class="relative flex flex-col">
        <li v-for="entry in entries" :key="entry.id" class="relative flex gap-3 pb-6 last:pb-0">
            <span class="absolute start-[19px] top-10 bottom-0 border-s border-dashed border-border" aria-hidden="true" />
            <span :class="['relative z-[1] flex size-10 shrink-0 items-center justify-center rounded-full border', eventTone(entry.event).badge]">
                <KtIcon :name="eventTone(entry.event).icon" class="text-lg" />
            </span>
            <div class="min-w-0 grow pt-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                    <span class="font-semibold text-mono">{{ eventLabel(entry.event) }}</span>
                    <template v-if="showSubject && entry.subject">
                        <span class="text-muted-foreground">·</span>
                        <span class="text-secondary-foreground">{{ subjectLabel(entry.subject) }}</span>
                        <Link v-if="entry.url" :href="entry.url" class="font-medium text-primary hover:underline">{{ entry.label }}</Link>
                        <span v-else-if="entry.label" class="font-medium text-mono">{{ entry.label }}</span>
                    </template>
                    <span v-if="entry.child" class="kt-badge kt-badge-sm kt-badge-outline">{{ subjectLabel(entry.child) }}<template v-if="!showSubject && entry.label"> · {{ entry.label }}</template></span>
                    <span v-if="entry.locale" class="kt-badge kt-badge-sm kt-badge-outline uppercase">{{ entry.locale }}</span>
                </div>
                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                    <span class="flex items-center gap-1.5">
                        <UserAvatar v-if="entry.causer?.avatar" :avatar="entry.causer.avatar" :name="entry.causer.name" :ring="false" size="size-5 text-[9px]" />
                        {{ entry.causer?.name ?? t('activity.system') }}
                    </span>
                    <time :datetime="entry.created_at" :title="entry.date">{{ entry.ago }}</time>
                    <span v-if="entry.ip" dir="ltr">{{ entry.ip }}</span>
                </div>

                <div v-if="entry.changes.length" class="mt-2.5 overflow-hidden rounded-lg border border-border">
                    <table class="w-full text-xs">
                        <tbody>
                            <tr v-for="change in entry.changes" :key="change.field" class="border-b border-border last:border-0">
                                <th scope="row" class="w-1/4 bg-muted/40 px-2.5 py-1.5 text-start font-medium text-secondary-foreground">{{ fieldLabel(change.field) }}</th>
                                <td class="px-2.5 py-1.5 text-destructive/90 line-through decoration-destructive/40">{{ value(change.old) }}</td>
                                <td class="w-5 text-center text-muted-foreground"><KtIcon name="arrow-left" class="ltr:rotate-180" /></td>
                                <td class="px-2.5 py-1.5 font-medium text-green-700 dark:text-green-400">{{ value(change.new) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <dl v-if="Object.keys(entry.details).length" class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                    <div v-for="(detail, key) in entry.details" :key="key" class="flex gap-1">
                        <dt>{{ fieldLabel(String(key)) }}:</dt>
                        <dd class="font-medium text-secondary-foreground">{{ value(detail as never) }}</dd>
                    </div>
                </dl>
            </div>
        </li>
    </ol>
</template>
