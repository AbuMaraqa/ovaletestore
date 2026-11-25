<script setup lang="ts">
import { ref, watch } from 'vue';
import ActivityTimeline from '@/Components/Activity/ActivityTimeline.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useActivityPanel } from '@/Composables/useActivityPanel';
import { useFocusTrap } from '@/Composables/useFocusTrap';
import { useTrans } from '@/Composables/useTrans';

/** Side drawer (Metronic kt-drawer look) with the history of the record chosen in a table row. */
const { state, close } = useActivityPanel();
const { t } = useTrans();

const panel = ref<HTMLElement | null>(null);
watch(() => state.open, (open) => document.body.classList.toggle('overflow-hidden', open));
// Keyboard: focus moves into the drawer, Tab stays inside, Esc closes, focus returns to the row button.
useFocusTrap(panel, () => state.open, { onEscape: close });
</script>

<template>
    <Teleport to="body">
        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition-opacity duration-200" leave-active-class="transition-opacity duration-200">
            <div v-if="state.open" class="fixed inset-0 z-[90] bg-black/30 backdrop-blur-[2px]" @click="close" />
        </Transition>
        <Transition
            enter-from-class="translate-x-full rtl:-translate-x-full"
            leave-to-class="translate-x-full rtl:-translate-x-full"
            enter-active-class="transition-transform duration-300"
            leave-active-class="transition-transform duration-200"
        >
            <aside
                v-if="state.open"
                ref="panel"
                class="fixed inset-y-0 end-0 z-[100] flex w-full max-w-[520px] flex-col border-s border-border bg-background shadow-xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="activity-panel-title"
                :aria-busy="state.loading"
                data-lenis-prevent
            >
                <header class="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
                    <div class="min-w-0">
                        <h2 id="activity-panel-title" class="text-base font-semibold text-mono">{{ t('activity.title') }}</h2>
                        <p v-if="state.label" class="truncate text-sm text-muted-foreground">{{ state.label }}</p>
                    </div>
                    <button type="button" class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" :aria-label="t('common.close')" @click="close"><KtIcon name="cross" /></button>
                </header>
                <div class="grow overflow-y-auto px-5 py-5">
                    <div v-if="state.loading" class="space-y-4" aria-busy="true">
                        <div v-for="i in 4" :key="i" class="flex gap-3">
                            <div class="size-10 animate-pulse rounded-full bg-muted" />
                            <div class="grow space-y-2 pt-1"><div class="h-3 w-1/3 animate-pulse rounded bg-muted" /><div class="h-3 w-2/3 animate-pulse rounded bg-muted" /></div>
                        </div>
                    </div>
                    <p v-else-if="state.error" class="text-sm text-destructive">{{ t('activity.load_failed') }}</p>
                    <div v-else-if="!state.entries.length" class="flex flex-col items-center gap-2 py-16 text-center text-muted-foreground">
                        <KtIcon name="time" class="text-4xl" />
                        <p>{{ t('activity.empty') }}</p>
                    </div>
                    <ActivityTimeline v-else :entries="state.entries" />
                </div>
            </aside>
        </Transition>
    </Teleport>
</template>
