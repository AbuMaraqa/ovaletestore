<script setup lang="ts">
import { ref, useId, watch } from 'vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useFocusTrap } from '@/Composables/useFocusTrap';
import { useTrans } from '@/Composables/useTrans';

/** KTUI modal markup, opened/closed by Vue (KTUI JS is not used). Focus is trapped and restored on close. */
const open = defineModel<boolean>('open', { required: true });
const props = withDefaults(defineProps<{ title: string; width?: string; role?: 'dialog' | 'alertdialog'; initialFocus?: string }>(), {
    width: 'max-w-lg',
    role: 'dialog',
    initialFocus: undefined,
});
const { t } = useTrans();
const dialog = ref<HTMLElement | null>(null);
const titleId = useId();

watch(open, (value) => document.body.classList.toggle('overflow-hidden', value));
useFocusTrap(dialog, () => open.value, {
    onEscape: () => (open.value = false),
    initial: () => (props.initialFocus ? dialog.value?.querySelector<HTMLElement>(props.initialFocus) ?? null : null),
});
</script>

<template>
    <Teleport to="body">
        <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition-opacity duration-150" leave-active-class="transition-opacity duration-150">
            <div v-if="open" class="kt-modal-backdrop z-[90]" @click="open = false" />
        </Transition>
        <Transition enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95" enter-active-class="transition duration-200" leave-active-class="transition duration-150">
            <div v-if="open" class="kt-modal open z-[100]" :role="role" aria-modal="true" :aria-labelledby="titleId" @click.self="open = false">
                <div ref="dialog" tabindex="-1" :class="['kt-modal-content top-[10%] w-full outline-none', width]">
                    <div class="kt-modal-header">
                        <h3 :id="titleId" class="kt-modal-title">{{ title }}</h3>
                        <button type="button" class="kt-modal-close" :aria-label="t('common.close')" @click="open = false"><KtIcon name="cross" /></button>
                    </div>
                    <div class="kt-modal-body"><slot /></div>
                    <div v-if="$slots.footer" class="kt-modal-footer"><slot name="footer" /></div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
