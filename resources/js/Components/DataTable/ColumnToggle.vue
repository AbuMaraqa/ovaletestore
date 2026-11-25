<script setup lang="ts">
import { nextTick, ref, useId } from 'vue';
import type { Column } from '@/Components/DataTable/types';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { onClickOutside } from '@/Composables/onClickOutside';
import { useTrans } from '@/Composables/useTrans';

/** Show / hide optional columns (remembered per viewer). */
defineProps<{ columns: Column[]; hidden: string[] }>();
const emit = defineEmits<{ toggle: [key: string] }>();
const { t } = useTrans();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const panelId = useId();
onClickOutside(root, () => (open.value = false));

/** Focus the first control on open; Esc closes and returns focus to the trigger. */
async function toggle(): Promise<void> {
    open.value = !open.value;
    if (open.value) {
        await nextTick();
        root.value?.querySelector<HTMLElement>(`#${CSS.escape(panelId)} select, #${CSS.escape(panelId)} input`)?.focus();
    }
}
function onPanelKey(event: KeyboardEvent): void {
    if (event.key !== 'Escape') return;
    open.value = false;
    root.value?.querySelector<HTMLElement>('button')?.focus();
}
</script>

<template>
    <div ref="root" class="relative">
        <button type="button" class="kt-btn kt-btn-outline kt-btn-icon" :title="t('table.columns')" :aria-label="t('table.columns')" :aria-expanded="open" aria-haspopup="true" :aria-controls="panelId" @click="toggle">
            <KtIcon name="setting-4" />
        </button>
        <div v-if="open" :id="panelId" class="animate-pop-in absolute end-0 top-full z-30 mt-2 w-56 rounded-xl border border-border bg-popover p-2 shadow-lg" @keydown="onPanelKey">
            <p class="px-2 pt-1 pb-2 text-xs font-semibold text-mono">{{ t('table.columns') }}</p>
            <label
                v-for="column in columns.filter((c) => !c.fixed && c.label)"
                :key="column.key"
                class="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-accent/60"
            >
                <input type="checkbox" class="kt-checkbox kt-checkbox-sm" :checked="!hidden.includes(column.key)" @change="emit('toggle', column.key)" />
                {{ column.label }}
            </label>
        </div>
    </div>
</template>
