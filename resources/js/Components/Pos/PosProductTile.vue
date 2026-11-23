<script setup lang="ts">
import { useTrans } from '@/Composables/useTrans';
import type { PosItem } from '@/types/pos';

defineProps<{ item: PosItem; quantity: number; disabled: boolean }>();
defineEmits<{ add: [] }>();
const { t } = useTrans();
</script>

<template>
    <button
        type="button"
        class="relative flex h-full flex-col overflow-hidden rounded-2xl bg-white text-start ring-1 transition active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
        :class="quantity ? 'ring-2 ring-brand-500' : 'ring-stone-200 hover:ring-stone-300'"
        :disabled="!item.available || disabled"
        :aria-label="`${t('pos.add')}: ${item.name}`"
        @click="$emit('add')"
    >
        <span class="aspect-square w-full bg-stone-50">
            <img v-if="item.image" :src="item.image" :alt="item.name" loading="lazy" decoding="async" class="size-full object-contain p-2" />
        </span>
        <span class="flex flex-1 flex-col gap-1 p-3">
            <span class="line-clamp-2 text-sm font-semibold leading-snug">{{ item.name }}</span>
            <span v-if="item.variant" class="text-xs text-stone-500">{{ item.variant }}</span>
            <span class="mt-auto flex items-center justify-between gap-2 pt-1">
                <span class="font-bold tabular-nums" :class="item.on_sale ? 'text-red-700' : ''" dir="ltr">{{ item.price_formatted }}</span>
                <span v-if="!item.available" class="text-xs font-semibold text-red-600">{{ t('pos.out_of_stock') }}</span>
            </span>
        </span>
        <span
            v-if="quantity"
            class="absolute end-2 top-2 flex min-w-8 items-center justify-center rounded-full bg-brand-600 px-2 text-sm font-bold leading-8 text-white tabular-nums shadow"
        >
            {{ quantity }}
        </span>
    </button>
</template>
