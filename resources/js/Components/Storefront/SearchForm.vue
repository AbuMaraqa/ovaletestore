<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Icon from '@/Components/Storefront/Icon.vue';
import { useTrans } from '@/Composables/useTrans';

const { t } = useTrans();
const params = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');
const q = ref(params.get('q') ?? '');

function submit(): void {
    const term = q.value.trim();
    router.get(route('storefront.shop'), term ? { q: term } : {});
}
</script>

<template>
    <form role="search" class="relative w-full max-w-xl" @submit.prevent="submit">
        <label for="site-search" class="sr-only">{{ t('common.search') }}</label>
        <Icon name="search" class="pointer-events-none absolute start-4 top-1/2 size-5 -translate-y-1/2 text-stone-400" />
        <input
            id="site-search"
            v-model="q"
            type="search"
            maxlength="100"
            enterkeyhint="search"
            :placeholder="t('common.search_placeholder')"
            class="h-11 w-full rounded-full border-0 bg-white ps-11 pe-4 text-sm ring-1 ring-stone-200 placeholder:text-stone-400 focus:ring-2 focus:ring-brand-500 focus:outline-none"
        />
    </form>
</template>
