<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import FormField from '@/Components/Forms/FormField.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';

/**
 * Site typeface: the bundled Tajawal or a licensed font uploaded per weight (e.g. OT Magnit Arabic).
 * The preview loads the chosen file (or the one already uploaded) with the FontFace API before saving.
 */
type FontForm = {
    font_family: 'tajawal' | 'custom';
    font_name: string;
    font_scope: 'all' | 'storefront';
    font_locales: string[];
    font_regular: File | null;
    font_medium: File | null;
    font_bold: File | null;
};
const form = defineModel<FontForm>({ required: true });
const props = defineProps<{ uploaded: Record<string, string | null>; locales: string[]; errors: Record<string, string> }>();
const localeNames: Record<string, string> = { ar: 'العربية', en: 'English', he: 'עברית' };
const { t } = useTrans();

const weights = [
    { weight: 400, field: 'font_regular', label: 'admin.font_regular' },
    { weight: 500, field: 'font_medium', label: 'admin.font_medium' },
    { weight: 700, field: 'font_bold', label: 'admin.font_bold' },
] as const;

const isCustom = computed(() => form.value.font_family === 'custom');
const previewFamily = ref<string | null>(null);
const previewError = ref(false);
let blobs: string[] = [];
let loaded: FontFace[] = [];

function setFile(field: (typeof weights)[number]['field'], event: Event): void {
    form.value[field] = (event.target as HTMLInputElement).files?.[0] ?? null;
}

/** Registers every available weight under a throwaway family name and shows the sample in it. */
async function refreshPreview(): Promise<void> {
    blobs.forEach((url) => URL.revokeObjectURL(url));
    loaded.forEach((face) => document.fonts.delete(face));
    blobs = [];
    loaded = [];
    previewFamily.value = null;
    previewError.value = false;
    if (!isCustom.value) return;

    const family = `site-font-preview-${Date.now()}`;
    const sources = weights
        .map(({ weight, field }) => {
            const file = form.value[field];
            const url = file ? URL.createObjectURL(file) : props.uploaded[weight];
            if (file && url) blobs.push(url);
            return url ? { weight, url } : null;
        })
        .filter((s): s is { weight: 400 | 500 | 700; url: string } => s !== null);
    if (!sources.length) return;

    try {
        for (const { weight, url } of sources) {
            const face = new FontFace(family, `url(${url})`, { weight: String(weight) });
            await face.load();
            document.fonts.add(face);
            loaded.push(face);
        }
        previewFamily.value = family;
    } catch {
        previewError.value = true;
    }
}

watch(() => [form.value.font_family, form.value.font_regular, form.value.font_medium, form.value.font_bold], refreshPreview, { immediate: true });
onBeforeUnmount(() => {
    blobs.forEach((url) => URL.revokeObjectURL(url));
    loaded.forEach((face) => document.fonts.delete(face));
});

const previewStyle = computed(() => (previewFamily.value ? { fontFamily: `'${previewFamily.value}', var(--font-sans)` } : {}));
</script>

<template>
    <div class="grid gap-5 rounded-xl border border-border p-5">
        <div>
            <h3 class="text-sm font-semibold text-mono">{{ t('admin.font_section') }}</h3>
            <p class="mt-1 text-xs text-muted-foreground">{{ t('admin.font_section_help') }}</p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2" role="radiogroup" :aria-label="t('admin.font_section')">
            <label
                v-for="option in (['tajawal', 'custom'] as const)"
                :key="option"
                :class="[
                    'flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary/40',
                    form.font_family === option ? 'border-primary bg-primary/5' : 'border-border hover:bg-accent/40',
                ]"
            >
                <input v-model="form.font_family" type="radio" class="sr-only" :value="option" />
                <span
                    :class="['mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border-2', form.font_family === option ? 'border-primary' : 'border-input']"
                    aria-hidden="true"
                >
                    <span v-if="form.font_family === option" class="size-2 rounded-full bg-primary" />
                </span>
                <span>
                    <span class="block text-sm font-medium text-mono">{{ t(`admin.font_${option}`) }}</span>
                    <span class="block text-xs text-muted-foreground">{{ t(`admin.font_${option}_help`) }}</span>
                </span>
            </label>
        </div>

        <template v-if="isCustom">
            <div class="grid gap-5 md:grid-cols-2">
                <FormField id="font_name" :label="t('admin.font_name')" :help="t('admin.font_name_help')" error-key="font_name" required>
                    <input id="font_name" v-model="form.font_name" class="kt-input" dir="ltr" maxlength="60" placeholder="OT Magnit Arabic" />
                </FormField>
                <FormField id="font_scope" :label="t('admin.font_scope')" error-key="font_scope">
                    <select id="font_scope" v-model="form.font_scope" class="kt-select">
                        <option value="all">{{ t('admin.font_scope_all') }}</option>
                        <option value="storefront">{{ t('admin.font_scope_storefront') }}</option>
                    </select>
                </FormField>
            </div>

            <FormField id="font_locales" :label="t('admin.font_locales')" :help="t('admin.font_locales_help')" error-key="font_locales">
                <div class="flex flex-wrap gap-4 pt-1">
                    <label v-for="l in locales" :key="l" class="flex items-center gap-2 text-sm">
                        <input v-model="form.font_locales" type="checkbox" class="kt-checkbox" :value="l" />
                        <span :lang="l">{{ localeNames[l] ?? l }}</span>
                    </label>
                </div>
            </FormField>

            <div class="grid gap-5 md:grid-cols-3">
                <FormField v-for="w in weights" :id="w.field" :key="w.field" :label="t(w.label)" :error="errors[w.field]" :required="w.weight === 400 && !uploaded[400]">
                    <p v-if="uploaded[w.weight]" class="mb-1.5 flex items-center gap-1.5 text-xs text-green-600">
                        <KtIcon name="check-circle" />{{ t('admin.font_uploaded') }}
                    </p>
                    <input :id="w.field" type="file" accept=".woff2,.woff,.ttf,.otf" class="kt-input" @change="setFile(w.field, $event)" />
                </FormField>
            </div>
            <p class="text-xs text-muted-foreground">{{ t('admin.font_files_help') }}</p>
        </template>

        <div class="rounded-lg bg-muted/50 p-5">
            <p class="mb-2 text-xs font-medium text-muted-foreground">{{ t('admin.font_preview') }}</p>
            <p v-if="previewError" class="text-sm text-destructive">{{ t('admin.font_invalid_file') }}</p>
            <div v-else :style="previewStyle" class="space-y-1 text-mono">
                <p class="text-2xl font-bold">{{ t('admin.font_preview_title') }}</p>
                <p class="text-base font-medium">{{ t('admin.font_preview_text') }}</p>
                <p class="text-sm" dir="ltr">Ovalete Store · 0123456789 · ₪ 149.90</p>
            </div>
        </div>
    </div>
</template>
