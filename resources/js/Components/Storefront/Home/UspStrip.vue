<script setup lang="ts">
import { ref } from 'vue';
import Icon from '@/Components/Storefront/Icon.vue';
import { useGsap } from '@/Composables/useMotion';
import { useTrans } from '@/Composables/useTrans';

const { t } = useTrans();
const root = ref<HTMLElement | null>(null);

const items = [
    { icon: 'globe', key: 'usp_global' },
    { icon: 'shield', key: 'usp_quality' },
    { icon: 'check', key: 'usp_offers' },
    { icon: 'truck', key: 'usp_secure' },
] as const;

useGsap(root, ({ gsap }) => {
    gsap.from('[data-usp]', {
        autoAlpha: 0, y: 30, duration: 0.7, stagger: 0.1, ease: 'power3.out',
        scrollTrigger: { trigger: root.value, start: 'top 90%', once: true },
    });
});
</script>

<template>
    <section ref="root" class="container-store relative z-10 -mt-10 lg:-mt-14">
        <ul class="grid grid-cols-2 gap-px overflow-hidden rounded-[2rem] bg-stone-200/70 shadow-lift ring-1 ring-stone-200/70 lg:grid-cols-4">
            <li v-for="item in items" :key="item.key" data-usp class="group flex flex-col gap-3 bg-white p-5 sm:flex-row sm:items-center sm:p-7">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-700 transition duration-300 group-hover:scale-110 group-hover:bg-brand-600 group-hover:text-white">
                    <Icon :name="item.icon" class="size-6" />
                </span>
                <div>
                    <p class="font-bold text-cocoa">{{ t(`storefront.${item.key}`) }}</p>
                    <p class="mt-0.5 text-sm leading-snug text-stone-500">{{ t(`storefront.${item.key}_text`) }}</p>
                </div>
            </li>
        </ul>
    </section>
</template>
