<script setup lang="ts">
import { useTrans } from '@/Composables/useTrans';

defineProps<{
    attributes: { id: number; name: string; display_type: 'button' | 'select' | 'color'; terms: { id: number; name: string; color: string | null }[] }[];
    selected: Record<number, number | null>;
    isAvailable: (attributeId: number, termId: number) => boolean;
}>();
const emit = defineEmits<{ select: [attributeId: number, termId: number] }>();
const { t } = useTrans();
</script>

<template>
    <div class="space-y-6">
        <fieldset v-for="attribute in attributes" :key="attribute.id">
            <legend class="mb-3 text-sm font-semibold text-cocoa">
                {{ attribute.name }}
                <span v-if="selected[attribute.id]" class="font-normal text-stone-500">
                    : {{ attribute.terms.find((term) => term.id === selected[attribute.id])?.name }}
                </span>
            </legend>

            <select
                v-if="attribute.display_type === 'select'"
                class="field max-w-xs"
                :value="selected[attribute.id] ?? ''"
                @change="emit('select', attribute.id, Number(($event.target as HTMLSelectElement).value))"
            >
                <option value="" disabled>{{ t('storefront.choose', { name: attribute.name }) }}</option>
                <option v-for="term in attribute.terms" :key="term.id" :value="term.id" :disabled="!isAvailable(attribute.id, term.id)">{{ term.name }}</option>
            </select>

            <div v-else class="flex flex-wrap gap-2">
                <button
                    v-for="term in attribute.terms"
                    :key="term.id"
                    type="button"
                    :aria-pressed="selected[attribute.id] === term.id"
                    :disabled="!isAvailable(attribute.id, term.id)"
                    :title="term.name"
                    :class="[
                        'relative inline-flex min-h-11 items-center justify-center gap-2 rounded-full px-5 text-sm font-medium ring-1 transition',
                        selected[attribute.id] === term.id ? 'bg-cocoa text-white ring-cocoa' : 'bg-white text-stone-700 ring-stone-200 hover:ring-stone-400',
                        'disabled:cursor-not-allowed disabled:text-stone-300 disabled:line-through disabled:ring-stone-100',
                    ]"
                    @click="emit('select', attribute.id, term.id)"
                >
                    <span v-if="attribute.display_type === 'color' && term.color" class="size-4 rounded-full ring-1 ring-black/10" :style="{ backgroundColor: term.color }" />
                    {{ term.name }}
                </button>
            </div>
        </fieldset>
    </div>
</template>
