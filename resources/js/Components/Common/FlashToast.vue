<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import type { SharedProps } from '@/types';

/** Shows server flash messages (success/error) as a polite live-region toast. */
const page = usePage<SharedProps>();
const message = ref<{ text: string; type: 'success' | 'error' } | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;

function show(): void {
    const { success, error } = page.props.flash;
    const text = error ?? success;
    if (!text) return;
    message.value = { text, type: error ? 'error' : 'success' };
    clearTimeout(timer);
    timer = setTimeout(() => (message.value = null), 4000);
}

watch(() => page.props.flash, show, { immediate: true, deep: true });
const off = router.on('finish', show);
onBeforeUnmount(() => {
    off();
    clearTimeout(timer);
});
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-4 z-[100] flex justify-center px-4" aria-live="polite" role="status">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="translate-y-2 opacity-0"
        >
            <div
                v-if="message"
                :class="[
                    'pointer-events-auto flex max-w-md items-center gap-3 rounded-2xl px-5 py-3 text-sm font-medium text-white shadow-lg',
                    message.type === 'error' ? 'bg-red-600' : 'bg-stone-900',
                ]"
            >
                <svg v-if="message.type === 'success'" viewBox="0 0 20 20" class="size-5 shrink-0 text-emerald-400" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" />
                </svg>
                <span>{{ message.text }}</span>
            </div>
        </Transition>
    </div>
</template>
