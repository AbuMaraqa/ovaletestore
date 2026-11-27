<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { ACCEPTED, MAX_MB, useStagedUploads } from '@/Composables/useStagedUploads';
import { useTrans } from '@/Composables/useTrans';

/**
 * Product gallery on both the add and the edit screen.
 *  - New images: drop or pick → uploaded at once with progress (useStagedUploads) → `v-model:uploads` holds their ids,
 *    attached when the product is saved. Drag (or the arrow buttons) to reorder them.
 *  - Saved images (edit): ★ makes one the main image, the bin removes it (asks first) — these act immediately.
 * The first image is the main one on the store.
 */
const props = withDefaults(
    defineProps<{ existing?: { id: number; url: string; name: string }[]; productId?: number | null; max?: number; error?: string }>(),
    { existing: () => [], productId: null, max: 20, error: undefined },
);
const uploads = defineModel<string[]>('uploads', { default: () => [] });
const { t } = useTrans();
const { confirm } = useConfirm();
const staged = useStagedUploads((ids) => (uploads.value = ids), () => props.max - props.existing.length);
const input = ref<HTMLInputElement | null>(null);
const over = ref(false);
const dragFrom = ref<number | null>(null);

defineExpose({ reset: staged.reset, busy: staged.busy });

const total = computed(() => props.existing.length + staged.items.length);
const isMain = (index: number, list: 'existing' | 'staged') => index === 0 && (list === 'existing' || props.existing.length === 0);

function onDrop(event: DragEvent): void {
    over.value = false;
    if (event.dataTransfer?.files.length) staged.add(event.dataTransfer.files);
}
function onPick(event: Event): void {
    const el = event.target as HTMLInputElement;
    if (el.files) staged.add(el.files);
    el.value = '';
}
function makeMain(id: number): void {
    const ids = [id, ...props.existing.map((m) => m.id).filter((m) => m !== id)];
    router.patch(route('admin.products.media.reorder', props.productId!), { ids }, { preserveScroll: true, preserveState: true });
}
async function removeSaved(id: number): Promise<void> {
    if (!(await confirm({ title: t('forms.remove_image_title'), message: t('forms.remove_image_message'), confirmLabel: t('common.delete') }))) return;
    router.delete(route('admin.products.media.destroy', [props.productId!, id]), { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <div class="space-y-4">
        <ul v-if="total" class="media-grid" :aria-label="t('admin.images')">
            <li v-for="(item, i) in existing" :key="`m${item.id}`" :class="['media-tile group', isMain(i, 'existing') && 'is-main']">
                <img :src="item.url" :alt="item.name" loading="lazy" />
                <span v-if="isMain(i, 'existing')" class="media-badge">{{ t('forms.main_image') }}</span>
                <div class="media-actions">
                    <button v-if="i > 0" type="button" class="media-btn" :aria-label="t('admin.make_main')" :title="t('admin.make_main')" @click="makeMain(item.id)"><KtIcon name="star" /></button>
                    <button type="button" class="media-btn text-destructive" :aria-label="t('common.delete')" :title="t('common.delete')" @click="removeSaved(item.id)"><KtIcon name="trash" /></button>
                </div>
            </li>
            <li
                v-for="(item, i) in staged.items"
                :key="item.key"
                :class="['media-tile group', isMain(i, 'staged') && 'is-main', item.status === 'error' && 'is-error']"
                draggable="true"
                @dragstart="dragFrom = i"
                @dragover.prevent
                @drop.prevent.stop="dragFrom !== null && staged.move(dragFrom, i); dragFrom = null"
            >
                <img :src="item.preview" :alt="item.name" />
                <span v-if="isMain(i, 'staged') && item.status !== 'error'" class="media-badge">{{ t('forms.main_image') }}</span>
                <span v-else-if="item.status === 'done'" class="media-badge media-badge-new">{{ t('forms.new_image') }}</span>
                <div v-if="item.status === 'uploading'" class="media-progress" role="progressbar" :aria-valuenow="item.progress" aria-valuemin="0" aria-valuemax="100">
                    <span :style="{ width: `${item.progress}%` }" />
                </div>
                <p v-if="item.status === 'error'" class="media-error">{{ item.error }}</p>
                <div class="media-actions">
                    <button v-if="i > 0" type="button" class="media-btn" :aria-label="t('forms.move_earlier')" @click="staged.move(i, i - 1)"><KtIcon name="arrow-right" class="ltr:rotate-180" /></button>
                    <button v-if="i < staged.items.length - 1" type="button" class="media-btn" :aria-label="t('forms.move_later')" @click="staged.move(i, i + 1)"><KtIcon name="arrow-left" class="ltr:rotate-180" /></button>
                    <button type="button" class="media-btn text-destructive" :aria-label="t('common.delete')" @click="staged.remove(item.key)"><KtIcon name="trash" /></button>
                </div>
            </li>
        </ul>

        <button
            type="button"
            :class="['media-drop', over && 'is-over', total && 'is-compact']"
            @click="input?.click()"
            @dragenter.prevent="over = true"
            @dragover.prevent="over = true"
            @dragleave.prevent="over = false"
            @drop.prevent="onDrop"
        >
            <KtIcon name="cloud-add" class="text-3xl text-primary" />
            <span class="text-sm font-medium text-foreground">{{ t('forms.drop_images') }}</span>
            <span class="text-xs text-secondary-foreground">{{ t('forms.drop_images_hint', { max: MAX_MB, count: max }) }}</span>
        </button>
        <input ref="input" type="file" class="sr-only" multiple :accept="ACCEPTED.join(',')" tabindex="-1" aria-hidden="true" @change="onPick" />
        <p v-if="error" class="form-field-error"><KtIcon name="information-2" class="text-sm" /> {{ error }}</p>
    </div>
</template>
