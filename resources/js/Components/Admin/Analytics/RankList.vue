<script setup lang="ts">
import { computed } from 'vue';
import KtCard from '@/Components/UI/KtCard.vue';
import { useTrans } from '@/Composables/useTrans';

/** A titled ranking (countries, browsers, sources…) with proportional bars. */
const props = defineProps<{ title: string; rows: { label: string; value: number; href?: string | null }[] }>();
const { t } = useTrans();

const max = computed(() => Math.max(1, ...props.rows.map((r) => r.value)));
const total = computed(() => props.rows.reduce((s, r) => s + r.value, 0));
const fmt = (n: number) => new Intl.NumberFormat(undefined).format(n);
</script>

<template>
    <KtCard :title="title">
        <ul v-if="rows.length" class="space-y-3">
            <li v-for="row in rows" :key="row.label" class="text-sm">
                <div class="mb-1 flex items-center justify-between gap-3">
                    <a v-if="row.href" :href="row.href" target="_blank" rel="noopener" class="min-w-0 truncate text-foreground hover:text-primary">{{ row.label }}</a>
                    <span v-else class="min-w-0 truncate text-foreground">{{ row.label }}</span>
                    <span class="shrink-0 tabular-nums text-muted-foreground" dir="ltr">
                        {{ fmt(row.value) }} <span class="text-xs">· {{ Math.round((row.value / Math.max(1, total)) * 100) }}%</span>
                    </span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-accent">
                    <div class="h-full rounded-full bg-primary/70 transition-[width] duration-700" :style="{ width: `${(row.value / max) * 100}%` }" />
                </div>
            </li>
        </ul>
        <p v-else class="text-sm text-muted-foreground">{{ t('analytics.no_data') }}</p>
    </KtCard>
</template>
