<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import KtIcon from '@/Components/UI/KtIcon.vue';

/** KTUI modal markup, opened/closed by Vue (KTUI JS is not used). */
const open = defineModel<boolean>('open', { required: true });
withDefaults(defineProps<{ title: string; width?: string }>(), { width: 'max-w-lg' });
const dialog = ref<HTMLElement | null>(null);

watch(open, async (value) => {
    document.body.classList.toggle('overflow-hidden', value);
    if (value) {
        await nextTick();
        dialog.value?.focus();
    }
});
</script>

<template>
    <Teleport to="body">
        <template v-if="open">
            <div class="kt-modal-backdrop z-[90]" @click="open = false" />
            <div class="kt-modal open z-[100]" role="dialog" aria-modal="true" :aria-label="title" @keydown.esc="open = false" @click.self="open = false">
                <div ref="dialog" tabindex="-1" :class="['kt-modal-content top-[10%] w-full outline-none', width]">
                    <div class="kt-modal-header">
                        <h3 class="kt-modal-title">{{ title }}</h3>
                        <button type="button" class="kt-modal-close" :aria-label="'×'" @click="open = false"><KtIcon name="cross" /></button>
                    </div>
                    <div class="kt-modal-body"><slot /></div>
                    <div v-if="$slots.footer" class="kt-modal-footer"><slot name="footer" /></div>
                </div>
            </div>
        </template>
    </Teleport>
</template>
