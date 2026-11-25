<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FlashToast from '@/Components/Common/FlashToast.vue';
import PageLoader from '@/Components/Common/PageLoader.vue';
import SeoHead from '@/Components/Common/SeoHead.vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

/** Full-screen, touch-first shell for sales reps on the iPad (no storefront chrome, no smooth scroll). */
const page = usePage<SharedProps>();
const { t } = useTrans();
const user = computed(() => page.props.auth.user);
const site = computed(() => page.props.site);
const logout = () => router.post(route('logout'));

const nav = computed(() => [
    { label: t('pos.new_order'), href: route('pos.index'), active: route().current('pos.index') || route().current('pos.create') },
    { label: t('pos.my_orders'), href: route('pos.orders'), active: route().current('pos.orders') || route().current('pos.orders.show') },
]);
</script>

<template>
    <SeoHead />
    <PageLoader content="#main" />
    <div class="flex min-h-dvh flex-col bg-stone-100 text-cocoa print:bg-white">
        <header class="sticky top-0 z-40 border-b border-stone-200 bg-white/95 backdrop-blur print:hidden">
            <div class="flex h-16 items-center gap-4 px-4 sm:px-6">
                <Link :href="route('pos.index')" class="flex items-center gap-3">
                    <img v-if="site.logo" :src="site.logo" alt="" class="size-10 rounded-full object-contain" />
                    <span class="leading-tight">
                        <span class="block text-base font-bold">{{ t('pos.title') }}</span>
                        <span class="block text-xs text-stone-500">{{ site.name }}</span>
                    </span>
                </Link>

                <nav class="ms-4 flex items-center gap-1" aria-label="POS">
                    <Link
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-full px-4 py-2.5 text-sm font-semibold transition"
                        :class="item.active ? 'bg-cocoa text-white' : 'text-stone-600 hover:bg-stone-100'"
                    >
                        {{ item.label }}
                    </Link>
                </nav>

                <div v-if="user" class="ms-auto flex items-center gap-3">
                    <UserAvatar :avatar="user.avatar" :name="user.name" size="size-9" :ring="false" />
                    <span class="hidden text-sm font-semibold sm:block">{{ user.name }}</span>
                    <a v-if="user.admin_url" :href="user.admin_url" class="hidden rounded-full px-3 py-2 text-sm text-brand-700 hover:bg-brand-50 md:block">{{ t('account.control_panel') }}</a>
                    <button type="button" class="rounded-full px-3 py-2 text-sm text-red-600 hover:bg-red-50" @click="logout">{{ t('common.logout') }}</button>
                </div>
            </div>
        </header>

        <main id="main" class="flex-1">
            <slot />
        </main>
    </div>
    <FlashToast />
</template>
