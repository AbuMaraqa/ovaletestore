<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';

/**
 * One image (category image, brand logo, site logo…): current image or the new pick as a preview, Change / Undo.
 * The File goes in the page form (`v-model`), sent with forceFormData. `id` lands on the button for labels/focus.
 */
const model = defineModel<File | null>({ required: true });
const props = withDefaults(defineProps<{ id: string; current?: string | null; accept?: string; shape?: 'square' | 'wide' | 'round' | 'banner'; fit?: 'cover' | 'contain' }>(), {
    current: null,
    accept: 'image/jpeg,image/png,image/webp,image/avif',
    shape: 'square',
    fit: 'cover',
});
const { t } = useTrans();
const input = ref<HTMLInputElement | null>(null);
const preview = ref<string | null>(null);
const shown = computed(() => preview.value ?? props.current);

function pick(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = file ? URL.createObjectURL(file) : null;
    model.value = file;
}
function undo(): void {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = null;
    model.value = null;
    if (input.value) input.value.value = '';
}
onBeforeUnmount(() => preview.value && URL.revokeObjectURL(preview.value));
</script>

<template>
    <div :class="['flex gap-4', shape === 'banner' ? 'flex-col items-start sm:flex-row sm:items-end' : 'items-center']">
        <button
            :id="id"
            type="button"
            :class="[
                'group relative shrink-0 overflow-hidden border border-border bg-muted/40 transition-colors hover:border-primary focus-visible:border-primary focus-visible:ring-3 focus-visible:ring-ring/30 focus-visible:outline-none',
                shape === 'banner' ? 'aspect-[3/2] w-full max-w-md rounded-xl' : shape === 'wide' ? 'aspect-[1.91/1] w-48 rounded-xl' : shape === 'round' ? 'size-24 rounded-full' : 'size-24 rounded-xl',
            ]"
            :aria-label="t('forms.choose_image')"
            @click="input?.click()"
        >
            <img v-if="shown" :src="shown" alt="" :class="['size-full', fit === 'contain' ? 'bg-white object-contain p-2' : 'object-cover']" />
            <span v-else class="flex size-full items-center justify-center text-2xl text-muted-foreground"><KtIcon name="picture" /></span>
            <span class="absolute inset-x-0 bottom-0 bg-black/55 py-1 text-[11px] text-white opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">
                {{ t('forms.change') }}
            </span>
        </button>
        <div class="flex flex-col items-start gap-1.5">
            <button type="button" class="kt-btn kt-btn-sm kt-btn-outline" @click="input?.click()"><KtIcon name="picture" /> {{ shown ? t('forms.change') : t('forms.choose_image') }}</button>
            <button v-if="model" type="button" class="kt-btn kt-btn-sm kt-btn-ghost" @click="undo">{{ t('forms.undo_pick') }}</button>
            <span v-if="model" class="max-w-48 truncate text-xs text-muted-foreground" dir="ltr">{{ model.name }}</span>
        </div>
        <input ref="input" type="file" class="sr-only" :accept="accept" tabindex="-1" aria-hidden="true" @change="pick" />
    </div>
</template>
