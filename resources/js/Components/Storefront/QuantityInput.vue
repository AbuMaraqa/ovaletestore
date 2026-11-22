<script setup lang="ts">
import Icon from '@/Components/Storefront/Icon.vue';
import { useTrans } from '@/Composables/useTrans';

const model = defineModel<number>({ required: true });
const props = withDefaults(defineProps<{ min?: number; max?: number; disabled?: boolean }>(), { min: 1, max: 999, disabled: false });
const emit = defineEmits<{ commit: [value: number] }>();
const { t } = useTrans();

function set(value: number): void {
    const clamped = Math.min(props.max, Math.max(props.min, Number.isFinite(value) ? Math.round(value) : props.min));
    model.value = clamped;
    emit('commit', clamped);
}
</script>

<template>
    <div class="inline-flex h-12 items-center rounded-full bg-white ring-1 ring-stone-200" :class="{ 'opacity-50': disabled }">
        <button type="button" class="inline-flex size-12 items-center justify-center rounded-full hover:bg-stone-50 disabled:opacity-40" :disabled="disabled || model <= min" :aria-label="`- ${t('storefront.quantity')}`" @click="set(model - 1)">
            <Icon name="minus" class="size-4" />
        </button>
        <input
            :value="model"
            type="number"
            inputmode="numeric"
            :min="min"
            :max="max"
            :disabled="disabled"
            :aria-label="t('storefront.quantity')"
            class="w-12 border-0 bg-transparent p-0 text-center text-sm font-semibold tabular-nums focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
            @change="set(Number(($event.target as HTMLInputElement).value))"
        />
        <button type="button" class="inline-flex size-12 items-center justify-center rounded-full hover:bg-stone-50 disabled:opacity-40" :disabled="disabled || model >= max" :aria-label="`+ ${t('storefront.quantity')}`" @click="set(model + 1)">
            <Icon name="plus" class="size-4" />
        </button>
    </div>
</template>
