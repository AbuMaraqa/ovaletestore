<script setup lang="ts">
import KtButton from '@/Components/UI/KtButton.vue';
import { useTrans } from '@/Composables/useTrans';

type Line = { number: string; label: Record<string, string> };

/** Labelled phone numbers (footer + contact page): add, reorder, remove. */
const lines = defineModel<Line[]>({ required: true });
const props = defineProps<{ locales: string[]; errors: Record<string, string> }>();
const { t } = useTrans();

const blankLabel = () => Object.fromEntries(props.locales.map((l) => [l, '']));
const add = () => lines.value.push({ number: '', label: blankLabel() });
const remove = (index: number) => lines.value.splice(index, 1);
const move = (index: number, by: number) => {
    const [line] = lines.value.splice(index, 1);
    lines.value.splice(index + by, 0, line);
};
const dirFor = (l: string) => (l === 'en' ? 'ltr' : 'rtl');
</script>

<template>
    <fieldset class="grid gap-3">
        <legend class="mb-1 text-sm font-semibold">{{ t('admin.phone_lines') }}</legend>
        <p class="text-xs text-muted-foreground">{{ t('admin.phone_lines_help') }}</p>

        <div v-for="(line, index) in lines" :key="index" class="grid gap-3 rounded-lg border border-border p-3 md:grid-cols-[10rem_1fr_auto]">
            <div>
                <label :for="`pl-${index}`" class="mb-1 block text-xs font-medium">{{ t('admin.phone') }}</label>
                <input :id="`pl-${index}`" v-model="line.number" class="kt-input" dir="ltr" inputmode="tel" placeholder="+970599000000" />
                <p v-if="errors[`phone_lines.${index}.number`]" class="mt-1 text-xs text-destructive">{{ errors[`phone_lines.${index}.number`] }}</p>
            </div>
            <div class="grid gap-2 sm:grid-cols-3">
                <div v-for="l in locales" :key="l">
                    <label :for="`pll-${index}-${l}`" class="mb-1 block text-xs font-medium">{{ t('admin.phone_label') }} ({{ l }})</label>
                    <input :id="`pll-${index}-${l}`" v-model="line.label[l]" class="kt-input" :dir="dirFor(l)" />
                </div>
            </div>
            <div class="flex items-end gap-1">
                <KtButton variant="ghost" size="sm" icon="arrow-up" icon-only :disabled="index === 0" :aria-label="t('admin.move_up')" @click="move(index, -1)" />
                <KtButton variant="ghost" size="sm" icon="arrow-down" icon-only :disabled="index === lines.length - 1" :aria-label="t('admin.move_down')" @click="move(index, 1)" />
                <KtButton variant="ghost" size="sm" icon="trash" icon-only :aria-label="t('common.delete')" @click="remove(index)" />
            </div>
        </div>

        <div><KtButton variant="outline" size="sm" icon="plus" @click="add">{{ t('admin.add_phone') }}</KtButton></div>
    </fieldset>
</template>
