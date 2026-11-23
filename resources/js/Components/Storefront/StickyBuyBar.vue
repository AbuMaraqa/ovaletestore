<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import Icon from '@/Components/Storefront/Icon.vue';
import { stickyBarVisible } from '@/Composables/useCountdown';

/**
 * Mobile-only buy bar, shown once the main buy button scrolls out of view (IntersectionObserver on `target`).
 */
const props = defineProps<{ target: HTMLElement | null; price: string | null; disabled: boolean; adding: boolean; label: string }>();
const emit = defineEmits<{ add: [] }>();
const visible = ref(false);
let observer: IntersectionObserver | undefined;

// The parent's template ref may only be set after this component mounts, so follow it.
watch(
    () => props.target,
    (el) => {
        observer?.disconnect();
        if (!el || typeof IntersectionObserver === 'undefined') return;
        observer = new IntersectionObserver(([entry]) => {
            // Only once the button has scrolled *above* the viewport, not before the visitor reached it.
            visible.value = !entry.isIntersecting && entry.boundingClientRect.top < 0;
            stickyBarVisible.value = visible.value;
        });
        observer.observe(el);
    },
    { immediate: true, flush: 'post' },
);

onBeforeUnmount(() => {
    observer?.disconnect();
    stickyBarVisible.value = false;
});
</script>

<template>
    <Transition enter-from-class="translate-y-full" leave-to-class="translate-y-full" enter-active-class="transition duration-300" leave-active-class="transition duration-200">
        <div v-if="visible" class="fixed inset-x-0 bottom-0 z-40 border-t border-stone-200 bg-white/95 px-4 py-3 shadow-lift backdrop-blur lg:hidden">
            <div class="flex items-center gap-3">
                <p class="shrink-0 text-xl font-extrabold text-cocoa" dir="ltr">{{ price }}</p>
                <button type="button" class="btn-accent h-12 flex-1 text-base" :disabled="disabled || adding" @click="emit('add')">
                    <span v-if="adding" class="size-4 animate-spin rounded-full border-2 border-white border-e-transparent" aria-hidden="true" />
                    <Icon v-else name="cart" class="size-5" />
                    {{ label }}
                </button>
            </div>
        </div>
    </Transition>
</template>
