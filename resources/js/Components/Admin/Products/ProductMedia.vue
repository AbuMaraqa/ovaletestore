<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import KtCard from '@/Components/UI/KtCard.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';

/** Gallery manager: upload (validated server-side), reorder (first = main image), delete. */
const props = defineProps<{ productId: number; media: { id: number; url: string; name: string; size: string }[] }>();
const { t } = useTrans();
const input = ref<HTMLInputElement | null>(null);
const upload = useForm<{ images: File[] }>({ images: [] });

function onFiles(event: Event): void {
    const files = Array.from((event.target as HTMLInputElement).files ?? []);
    if (!files.length) return;
    upload.images = files;
    upload.post(route('admin.products.media.store', props.productId), {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            upload.reset();
            if (input.value) input.value.value = '';
        },
    });
}

function makeMain(id: number): void {
    const ids = [id, ...props.media.map((m) => m.id).filter((m) => m !== id)];
    router.patch(route('admin.products.media.reorder', props.productId), { ids }, { preserveScroll: true });
}

function remove(id: number): void {
    if (!confirm(t('common.confirm_delete'))) return;
    router.delete(route('admin.products.media.destroy', [props.productId, id]), { preserveScroll: true });
}
</script>

<template>
    <KtCard :title="t('admin.images')" :description="t('admin.images_help')">
        <template #toolbar>
            <label class="kt-btn kt-btn-outline cursor-pointer">
                <KtIcon name="picture" /> {{ t('admin.upload_images') }}
                <input ref="input" type="file" class="sr-only" multiple accept="image/jpeg,image/png,image/webp,image/avif" @change="onFiles" />
            </label>
        </template>

        <div v-if="upload.progress" class="mb-4 h-1.5 overflow-hidden rounded-full bg-muted">
            <div class="h-full bg-primary transition-all" :style="{ width: `${upload.progress.percentage}%` }" />
        </div>
        <p v-for="(message, key) in upload.errors" :key="key" class="mb-2 text-sm text-destructive">{{ message }}</p>

        <ul v-if="media.length" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            <li v-for="(item, i) in media" :key="item.id" class="group relative overflow-hidden rounded-lg border border-border bg-white">
                <img :src="item.url" :alt="item.name" class="aspect-square w-full object-contain p-2" loading="lazy" />
                <span v-if="i === 0" class="kt-badge kt-badge-primary kt-badge-sm absolute start-2 top-2">★</span>
                <div class="flex items-center justify-between gap-1 border-t border-border p-1.5 text-xs text-muted-foreground">
                    <span>{{ item.size }}</span>
                    <span class="flex gap-1">
                        <button v-if="i > 0" type="button" class="kt-btn kt-btn-xs kt-btn-ghost" :title="t('admin.make_main')" @click="makeMain(item.id)"><KtIcon name="star" /></button>
                        <button type="button" class="kt-btn kt-btn-xs kt-btn-ghost text-destructive" :title="t('common.delete')" @click="remove(item.id)"><KtIcon name="trash" /></button>
                    </span>
                </div>
            </li>
        </ul>
        <p v-else class="text-sm text-muted-foreground">{{ t('common.no_results') }}</p>
    </KtCard>
</template>
