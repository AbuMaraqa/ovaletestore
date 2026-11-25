<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { PageMeta } from '@/Components/DataTable/types';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';

/** Metronic datatable footer: "Show [25] per page" · "1-25 of 859" · ← 1 2 3 … → */
const props = defineProps<{ meta: PageMeta; perPage: number; options?: number[] }>();
const emit = defineEmits<{ perPage: [value: number] }>();
const { t } = useTrans();

const pages = computed(() => props.meta.links.slice(1, -1));
const prev = computed(() => props.meta.links[0]?.url ?? null);
const next = computed(() => props.meta.links[props.meta.links.length - 1]?.url ?? null);
</script>

<template>
    <div class="flex w-full flex-col items-center justify-between gap-3 text-sm text-secondary-foreground sm:flex-row">
        <label class="flex items-center gap-2">
            {{ t('table.show') }}
            <select class="kt-select kt-select-sm w-20" :value="perPage" @change="emit('perPage', Number(($event.target as HTMLSelectElement).value))">
                <option v-for="n in options ?? [10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
            </select>
            {{ t('table.per_page') }}
        </label>
        <div class="flex items-center gap-4">
            <span class="tabular-nums">{{ t('table.range', { from: meta.from ?? 0, to: meta.to ?? 0, total: meta.total }) }}</span>
            <nav v-if="meta.last_page > 1" class="flex items-center gap-1" :aria-label="t('table.pagination')">
                <component :is="prev ? Link : 'span'" :href="prev ?? undefined" preserve-scroll preserve-state :class="['kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost', { 'pointer-events-none opacity-40': !prev }]" :aria-label="t('table.previous')">
                    <KtIcon name="arrow-right" class="ltr:rotate-180" />
                </component>
                <template v-for="(link, i) in pages" :key="i">
                    <Link v-if="link.url" :href="link.url" preserve-scroll preserve-state :class="['kt-btn kt-btn-sm min-w-8 justify-center tabular-nums', link.active ? 'kt-btn-outline border-primary text-primary' : 'kt-btn-ghost']" :aria-current="link.active ? 'page' : undefined">{{ link.label }}</Link>
                    <span v-else class="px-1 text-muted-foreground">…</span>
                </template>
                <component :is="next ? Link : 'span'" :href="next ?? undefined" preserve-scroll preserve-state :class="['kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost', { 'pointer-events-none opacity-40': !next }]" :aria-label="t('table.next')">
                    <KtIcon name="arrow-left" class="ltr:rotate-180" />
                </component>
            </nav>
        </div>
    </div>
</template>
