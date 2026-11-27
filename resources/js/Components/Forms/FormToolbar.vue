<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';

/**
 * Sticky page header of a form: back, title (+ status slot), "unsaved changes" pill, and the actions
 * (Discard while dirty, Save — also Ctrl/Cmd+S via useFormKit). Stays under the fixed admin header while the
 * page scrolls, so Save is always one click away; gets its bottom border only once it is stuck.
 */
const props = withDefaults(
    defineProps<{ title: string; backHref?: string; formId: string; dirty?: boolean; processing?: boolean; saveLabel?: string; progress?: number | null; readonly?: boolean }>(),
    { backHref: undefined, dirty: false, processing: false, saveLabel: undefined, progress: null, readonly: false },
);
defineEmits<{ discard: [] }>();
const { t } = useTrans();

const sentinel = ref<HTMLElement | null>(null);
const stuck = ref(false);
let observer: IntersectionObserver | undefined;
onMounted(() => {
    if (!sentinel.value) return;
    const offset = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--header-height')) || 70;
    observer = new IntersectionObserver(([entry]) => (stuck.value = !entry.isIntersecting), { rootMargin: `-${offset + 1}px 0px 0px 0px` });
    observer.observe(sentinel.value);
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div ref="sentinel" aria-hidden="true" />
    <div class="form-toolbar" :data-stuck="stuck || undefined">
        <div class="flex min-w-0 items-center gap-3">
            <Link v-if="backHref" :href="backHref" class="kt-btn kt-btn-icon kt-btn-ghost shrink-0" :aria-label="t('common.back')">
                <KtIcon name="arrow-right" class="ltr:rotate-180" />
            </Link>
            <h1 class="truncate text-lg font-semibold text-mono">{{ title }}</h1>
            <slot name="status" />
            <Transition enter-active-class="transition duration-150" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
                <span v-if="dirty" class="kt-badge kt-badge-sm kt-badge-warning kt-badge-outline hidden shrink-0 gap-1.5 sm:inline-flex">
                    <span class="size-1.5 rounded-full bg-warning" /> {{ t('forms.unsaved') }}
                </span>
            </Transition>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
            <button v-if="dirty && !readonly" type="button" class="kt-btn kt-btn-ghost max-sm:hidden" :disabled="processing" @click="$emit('discard')">{{ t('forms.discard') }}</button>
            <button v-if="!readonly" type="submit" :form="formId" class="kt-btn" :disabled="processing" :aria-busy="processing" :title="`${props.saveLabel ?? t('common.save')} (Ctrl+S)`">
                <span v-if="processing" class="size-4 animate-spin rounded-full border-2 border-current border-e-transparent" aria-hidden="true" />
                <KtIcon v-else name="check" />
                {{ saveLabel ?? t('common.save') }}
            </button>
        </div>
        <div v-if="progress !== null" class="absolute inset-x-0 bottom-0 h-0.5 bg-primary transition-[width]" :style="{ width: `${progress}%` }" />
    </div>
</template>
