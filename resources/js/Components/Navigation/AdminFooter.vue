<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();
const { t } = useTrans();
const year = new Date().getFullYear();
</script>

<template>
    <footer class="kt-footer">
        <div class="kt-container-fixed">
            <div class="flex flex-col items-center justify-center gap-3 py-5 md:flex-row md:justify-between">
                <div class="order-2 flex gap-2 text-sm font-normal md:order-1">
                    <span class="text-secondary-foreground">{{ year }}©</span>
                    <span class="text-secondary-foreground">{{ page.props.site.name }}</span>
                </div>
                <nav class="order-1 flex gap-4 text-sm font-normal text-secondary-foreground md:order-2">
                    <a :href="route('storefront.home')" target="_blank" rel="noopener" class="hover:text-primary">{{ t('admin.view_store') }}</a>
                    <a href="/sitemap.xml" target="_blank" rel="noopener" class="hover:text-primary">Sitemap</a>
                </nav>
            </div>
        </div>
    </footer>
</template>
