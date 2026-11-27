<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import type { ColumnDef } from '@/Components/Table/types';
import KtBadge from '@/Components/UI/KtBadge.vue';
import type { Avatar } from '@/types';

/** Draws one cell from its server column definition (App\Tables\Column) and the row. */
const props = defineProps<{ column: ColumnDef; row: Record<string, unknown> }>();

const get = (field?: string): unknown => (field ? field.split('.').reduce<unknown>((v, k) => (v as Record<string, unknown> | null)?.[k], props.row) : undefined);
const value = computed(() => get(props.column.field));
const text = computed(() => {
    const v = value.value;
    if (v === null || v === undefined || v === '') return '—';
    return Array.isArray(v) ? v.join('، ') || '—' : String(v);
});
const href = computed(() => (get(props.column.href) as string | null | undefined) ?? null);
const sub = computed(() => (get(props.column.sub) as string | null | undefined) ?? null);
const avatar = computed(() => (props.column.avatar ? (get(props.column.avatar) as Avatar | null) : null));
const image = computed(() => (props.column.image ? ((get(props.column.image) as string | null) ?? '') : null));
const title = computed(() => (get(props.column.title) as string | undefined) ?? undefined);
const chips = computed(() => (props.column.chips ?? []).filter((c) => get(c.when)));
const badge = computed(() => props.column.badges?.[String(value.value)] ?? null);
const isNumeric = computed(() => ['money', 'number'].includes(props.column.type));
const textClass = computed(() => [
    props.column.strong || props.column.href ? 'font-medium text-mono' : '',
    props.column.muted || props.column.type === 'date' ? 'text-sm text-secondary-foreground' : '',
    props.column.mono ? 'font-mono text-xs' : '',
    isNumeric.value ? 'whitespace-nowrap tabular-nums' : '',
    props.column.type === 'money' ? 'font-semibold' : '',
    props.column.truncate ? 'block max-w-56 truncate' : '',
    href.value ? 'hover:text-primary' : '',
]);
</script>

<template>
    <KtBadge v-if="column.type === 'badge'" :variant="(badge?.variant as any) ?? 'secondary'">{{ badge?.label ?? text }}</KtBadge>
    <KtBadge v-else-if="column.type === 'boolean'" :variant="value ? 'success' : 'secondary'">{{ value ? column.labels?.true : column.labels?.false }}</KtBadge>

    <div v-else :class="['flex items-center gap-3', (avatar || image !== null) && 'min-w-56']">
        <UserAvatar v-if="avatar" :avatar="avatar" :name="text" :ring="false" />
        <template v-else-if="image !== null">
            <img v-if="image" :src="image" alt="" class="size-11 shrink-0 rounded-lg border border-border bg-white object-contain" loading="lazy" width="44" height="44" />
            <span v-else class="size-11 shrink-0 rounded-lg bg-muted" />
        </template>
        <div class="min-w-0">
            <component :is="href ? Link : 'span'" :href="href ?? undefined" :class="textClass" :dir="column.ltr || isNumeric ? 'ltr' : undefined" :title="title">{{ text }}</component>
            <p v-if="sub" class="truncate text-xs text-muted-foreground" dir="auto">{{ sub }}</p>
            <div v-if="chips.length" class="mt-1 flex flex-wrap gap-1.5">
                <KtBadge v-for="chip in chips" :key="chip.label" :variant="(chip.variant as any) ?? 'secondary'" size="sm">
                    {{ chip.label }}<template v-if="chip.count"> · {{ get(chip.count) }}</template>
                </KtBadge>
            </div>
        </div>
    </div>
</template>
