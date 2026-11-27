<script setup lang="ts">
import { nextTick, ref, useId } from 'vue';
import type { Filter } from '@/Components/DataTable/types';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { onClickOutside } from '@/Composables/onClickOutside';
import { useTrans } from '@/Composables/useTrans';

/** Filament-style "Filters" dropdown: every filter of the screen in one panel, with a counter and reset. */
const props = defineProps<{ filters: Filter[]; values: Record<string, string>; count: number }>();
const emit = defineEmits<{ change: [key: string, value: string]; reset: [] }>();
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

const value = (key: string) => props.values[key] ?? '';
</script>

<template>
    <div ref="root" class="relative">
        <button type="button" :class="['kt-btn kt-btn-outline', { 'border-primary text-primary': count }]" :aria-expanded="open" aria-haspopup="dialog" :aria-controls="panelId" @click="toggle">
            <KtIcon name="filter" />
            {{ t('table.filters') }}
            <span v-if="count" class="kt-badge kt-badge-sm kt-badge-primary rounded-full tabular-nums">{{ count }}</span>
        </button>
        <Transition leave-active-class="transition duration-100 ease-in" leave-to-class="opacity-0 -translate-y-1">
            <div v-if="open" :id="panelId" class="animate-pop-in absolute end-0 top-full z-30 mt-2 w-[min(300px,calc(100vw-2rem))] rounded-xl border border-border bg-popover p-4 shadow-lg" role="dialog" :aria-label="t('table.filters')" @keydown="onPanelKey">
                <div class="mb-3 flex items-center justify-between">
                    <span class="text-sm font-semibold text-mono">{{ t('table.filters') }}</span>
                    <button v-if="count" type="button" class="text-xs font-medium text-destructive hover:underline" @click="emit('reset')">{{ t('table.reset') }}</button>
                </div>
                <div class="flex flex-col gap-3.5">
                    <template v-for="filter in filters" :key="filter.key">
                        <label v-if="filter.type === 'select'" class="flex flex-col gap-1.5">
                            <span class="text-xs font-medium text-secondary-foreground">{{ filter.label }}</span>
                            <select class="kt-select" :value="value(filter.key)" @change="emit('change', filter.key, ($event.target as HTMLSelectElement).value)">
                                <option value="">{{ t('table.all') }}</option>
                                <option v-for="option in filter.options" :key="option.value" :value="String(option.value)">{{ option.label }}</option>
                            </select>
                        </label>
                        <fieldset v-else class="flex flex-col gap-1.5">
                            <legend class="mb-1.5 text-xs font-medium text-secondary-foreground">{{ filter.label }}</legend>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex flex-col gap-1">
                                    <span class="text-[11px] text-muted-foreground">{{ t('table.from') }}</span>
                                    <input type="date" class="kt-input" :value="value('from')" @change="emit('change', 'from', ($event.target as HTMLInputElement).value)" />
                                </label>
                                <label class="flex flex-col gap-1">
                                    <span class="text-[11px] text-muted-foreground">{{ t('table.until') }}</span>
                                    <input type="date" class="kt-input" :value="value('until')" @change="emit('change', 'until', ($event.target as HTMLInputElement).value)" />
                                </label>
                            </div>
                        </fieldset>
                    </template>
                </div>
            </div>
        </Transition>
    </div>
</template>
