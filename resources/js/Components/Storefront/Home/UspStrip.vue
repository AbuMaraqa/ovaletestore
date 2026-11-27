<script setup lang="ts">
import Icon from '@/Components/Storefront/Icon.vue';
import { useTrans } from '@/Composables/useTrans';

/** The store's four promises as one quiet line between hairlines — information, not decoration. */
const { t } = useTrans();

const items = [
    { icon: 'globe', key: 'usp_global' },
    { icon: 'shield', key: 'usp_quality' },
    { icon: 'check', key: 'usp_offers' },
    { icon: 'truck', key: 'usp_secure' },
] as const;
</script>

<template>
    <section class="container-store mt-12 lg:mt-16">
        <ul class="grid grid-cols-2 gap-x-6 gap-y-6 border-y border-hairline py-7 lg:grid-cols-4 lg:gap-x-10">
            <li v-for="item in items" :key="item.key" class="flex items-start gap-3">
                <Icon :name="item.icon" class="mt-0.5 size-6 shrink-0 text-brand-600" />
                <div>
                    <p class="font-bold text-cocoa">{{ t(`storefront.${item.key}`) }}</p>
                    <p class="mt-0.5 text-sm leading-snug text-stone-500">{{ t(`storefront.${item.key}_text`) }}</p>
                </div>
            </li>
        </ul>
    </section>
</template>
