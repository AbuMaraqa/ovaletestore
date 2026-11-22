<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { adminMenu, type MenuLink } from '@/Components/Navigation/adminMenu';
import KtIcon from '@/Components/UI/KtIcon.vue';
import KtModal from '@/Components/UI/KtModal.vue';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

/** Metronic-style search modal: jump to any admin page, or search products/orders by name, SKU or number. */
const open = defineModel<boolean>('open', { required: true });
const page = usePage<SharedProps>();
const { t } = useTrans();
const q = ref('');

const pages = computed(() => {
    const allowed = new Set(page.props.auth.permissions);
    const links: MenuLink[] = adminMenu.flatMap((s) => s.items.flatMap((i) => (i.children ?? [i as MenuLink])));
    return links.filter((l) => allowed.has(l.permission) && t(l.label).toLowerCase().includes(q.value.trim().toLowerCase()));
});

watch(open, (v) => v && (q.value = ''));

function go(url: string): void {
    open.value = false;
    router.visit(url);
}
</script>

<template>
    <KtModal v-model:open="open" :title="t('common.search')" width="max-w-xl">
        <div class="relative">
            <KtIcon name="magnifier" class="absolute start-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <input v-model="q" type="search" class="kt-input ps-9" :placeholder="t('admin.search_products')" autofocus @keydown.enter="q && go(`${route('admin.products.index')}?filter[search]=${encodeURIComponent(q)}`)" />
        </div>
        <ul class="mt-4 space-y-1">
            <li v-if="q && page.props.auth.permissions.includes('products.view')">
                <button type="button" class="kt-dropdown-menu-link w-full" @click="go(`${route('admin.products.index')}?filter[search]=${encodeURIComponent(q)}`)">
                    <KtIcon name="basket" /> {{ t('admin.products') }}: “{{ q }}”
                </button>
            </li>
            <li v-if="q && page.props.auth.permissions.includes('orders.view')">
                <button type="button" class="kt-dropdown-menu-link w-full" @click="go(`${route('admin.orders.index')}?filter[search]=${encodeURIComponent(q)}`)">
                    <KtIcon name="handcart" /> {{ t('admin.orders') }}: “{{ q }}”
                </button>
            </li>
            <li v-for="link in pages" :key="link.label + JSON.stringify(link.params ?? {})">
                <button type="button" class="kt-dropdown-menu-link w-full" @click="go(route(link.route, link.params ?? {}))">
                    <KtIcon name="arrow-right" class="rtl:rotate-180" /> {{ t(link.label) }}
                </button>
            </li>
        </ul>
    </KtModal>
</template>
