<script setup lang="ts">
import { useTrans } from '@/Composables/useTrans';
import type { PosItem } from '@/types/pos';

defineProps<{ item: PosItem; quantity: number; lineTotal: string; atMax: boolean }>();
const emit = defineEmits<{ set: [qty: number] }>();
const { t } = useTrans();

function onInput(event: Event): void {
    const value = parseInt((event.target as HTMLInputElement).value, 10);
    if (!Number.isNaN(value)) emit('set', value);
}
</script>

<template>
    <div class="flex items-center gap-3 py-3">
        <div class="min-w-0 flex-1">
            <p class="line-clamp-2 text-sm font-semibold leading-snug">{{ item.name }}</p>
            <p class="mt-0.5 text-xs text-stone-500">
                <span v-if="item.variant">{{ item.variant }} · </span><span dir="ltr">{{ item.price_formatted }}</span>
            </p>
            <p v-if="atMax && item.max !== null" class="mt-0.5 text-xs text-amber-700">{{ t('pos.max_reached', { count: item.max }) }}</p>
        </div>
        <div class="flex shrink-0 items-center rounded-full bg-stone-100" dir="ltr">
            <button type="button" class="size-10 rounded-full text-xl font-bold hover:bg-stone-200" :aria-label="`−1 ${item.name}`" @click="emit('set', quantity - 1)">−</button>
            <input
                :value="quantity"
                type="number"
                inputmode="numeric"
                min="0"
                :aria-label="`${t('pos.quantity')}: ${item.name}`"
                class="w-12 border-0 bg-transparent p-0 text-center font-bold tabular-nums focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                @change="onInput"
            />
            <button type="button" class="size-10 rounded-full text-xl font-bold hover:bg-stone-200 disabled:opacity-40" :disabled="atMax" :aria-label="`+1 ${item.name}`" @click="emit('set', quantity + 1)">+</button>
        </div>
        <span class="w-20 shrink-0 text-end text-sm font-bold tabular-nums" dir="ltr">{{ lineTotal }}</span>
    </div>
</template>
