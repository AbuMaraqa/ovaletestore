<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useTrans } from '@/Composables/useTrans';

defineProps<{ status: number }>();
const { t } = useTrans();
</script>

<template>
    <div class="container-store flex flex-col items-center py-24 text-center">
        <p class="text-7xl font-bold text-brand-200 tabular-nums">{{ status }}</p>
        <h1 class="mt-4 text-2xl font-bold text-cocoa">{{ status === 404 ? t('storefront.not_found_title') : t(`http-statuses.${status}`) }}</h1>
        <p v-if="status === 404" class="mt-3 max-w-md text-stone-600">{{ t('storefront.not_found_text') }}</p>
        <Link :href="route('storefront.home')" class="btn-primary mt-8">{{ t('common.home') }}</Link>
    </div>
</template>
