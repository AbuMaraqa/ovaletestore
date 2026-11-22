<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { onClickOutside } from '@/Composables/onClickOutside';
import { useLocale } from '@/Composables/useLocale';
import { useTheme } from '@/Composables/useTheme';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

const emit = defineEmits<{ openSidebar: [] }>();
const page = usePage<SharedProps>();
const { t } = useTrans();
const { available, current } = useLocale();
const { isDark, toggle } = useTheme();

const menuOpen = ref(false);
const menu = ref<HTMLElement | null>(null);
onClickOutside(menu, () => (menuOpen.value = false));

const logout = () => router.post(route('logout'));
</script>

<template>
    <header class="kt-header fixed end-0 top-0 z-10 flex h-[70px] items-stretch border-b border-border bg-background/95 backdrop-blur start-0">
        <div class="kt-container-fixed flex w-full items-center justify-between gap-4 px-4 lg:px-8">
            <div class="flex items-center gap-2">
                <button type="button" class="kt-btn kt-btn-icon kt-btn-ghost lg:hidden" :aria-label="t('common.menu')" @click="emit('openSidebar')">
                    <KtIcon name="burger-menu-2" />
                </button>
                <slot name="title" />
            </div>

            <div class="flex items-center gap-1.5">
                <a :href="route('storefront.home')" target="_blank" rel="noopener" class="kt-btn kt-btn-ghost kt-btn-sm hidden sm:inline-flex">
                    <KtIcon name="shop" /> {{ t('admin.view_store') }}
                </a>
                <a v-for="locale in available" :key="locale.code" :href="locale.url" :class="['kt-btn kt-btn-sm', locale.code === current ? 'kt-btn-secondary' : 'kt-btn-ghost']" :lang="locale.code">
                    {{ locale.code.toUpperCase() }}
                </a>
                <button type="button" class="kt-btn kt-btn-icon kt-btn-ghost" :aria-label="t('admin.theme_toggle')" @click="toggle">
                    <KtIcon :name="isDark ? 'sun' : 'moon'" />
                </button>

                <div v-if="page.props.auth.user" ref="menu" class="relative">
                    <button type="button" class="flex items-center gap-2 rounded-full p-0.5 ring-1 ring-border" :aria-expanded="menuOpen" @click="menuOpen = !menuOpen">
                        <img :src="page.props.auth.user.avatar" :alt="page.props.auth.user.name" class="size-9 rounded-full" width="36" height="36" />
                    </button>
                    <div v-if="menuOpen" class="absolute end-0 top-full z-50 mt-2 w-60 rounded-xl border border-border bg-popover p-2 shadow-lg">
                        <div class="border-b border-border px-3 pb-3 pt-1">
                            <p class="truncate text-sm font-semibold text-mono">{{ page.props.auth.user.name }}</p>
                        </div>
                        <button type="button" class="mt-1 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-foreground hover:bg-accent" @click="logout">
                            <KtIcon name="exit-right" /> {{ t('common.logout') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>
