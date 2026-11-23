<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { adminMenu } from '@/Components/Navigation/adminMenu';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import AdminNotifications from '@/Components/Navigation/AdminNotifications.vue';
import AdminSearch from '@/Components/Navigation/AdminSearch.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { onClickOutside } from '@/Composables/onClickOutside';
import { useLocale } from '@/Composables/useLocale';
import { useTheme } from '@/Composables/useTheme';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

/** Port of the Metronic demo1 header + topbar (search, notifications, language, theme, user dropdown). */
const emit = defineEmits<{ openSidebar: [] }>();
const page = usePage<SharedProps & { admin_badges: { pending_orders: number } | null }>();
const { t } = useTrans();
const { available, current } = useLocale();
const { isDark, toggle } = useTheme();

const user = computed(() => page.props.auth.user);
const pending = computed(() => page.props.admin_badges?.pending_orders ?? 0);
const title = computed(() => {
    const active = adminMenu.flatMap((s) => s.items).find((i) =>
        i.children ? i.children.some((c) => route().current(c.match)) : i.match && route().current(i.match),
    );
    return active ? t(active.label) : t('admin.dashboard');
});

const searchOpen = ref(false);
const userMenu = ref(false);
const userRoot = ref<HTMLElement | null>(null);
onClickOutside(userRoot, () => (userMenu.value = false));
const logout = () => router.post(route('logout'));
</script>

<template>
    <header id="header" class="kt-header fixed end-0 top-0 z-10 flex shrink-0 items-stretch border-b border-border bg-background/95 backdrop-blur start-0">
        <div id="headerContainer" class="kt-container-fixed flex items-stretch justify-between lg:gap-4">
            <!-- Mobile logo + drawer toggle -->
            <div class="-ms-1 flex items-center gap-2.5 lg:hidden">
                <Link :href="route('admin.dashboard')" class="shrink-0">
                    <img v-if="page.props.site.logo" :src="page.props.site.logo" alt="" class="max-h-[30px] w-auto" />
                </Link>
                <button type="button" class="kt-btn kt-btn-icon kt-btn-ghost" :aria-label="t('common.menu')" @click="emit('openSidebar')">
                    <KtIcon name="menu" />
                </button>
            </div>

            <!-- Breadcrumb -->
            <div class="hidden items-center gap-1.25 text-sm lg:flex">
                <Link :href="route('admin.dashboard')" class="text-secondary-foreground hover:text-primary">{{ t('admin.panel') }}</Link>
                <KtIcon name="right" class="text-[10px] text-muted-foreground rtl:rotate-180" />
                <span class="font-medium text-mono">{{ title }}</span>
            </div>

            <!-- Topbar -->
            <div class="flex items-center gap-2.5">
                <button type="button" class="group kt-btn kt-btn-ghost kt-btn-icon size-9 rounded-full hover:bg-primary/10" :aria-label="t('common.search')" @click="searchOpen = true">
                    <KtIcon name="magnifier" class="text-lg group-hover:text-primary" />
                </button>

                <AdminNotifications v-if="page.props.admin_badges" />

                <button type="button" class="group kt-btn kt-btn-ghost kt-btn-icon size-9 rounded-full hover:bg-primary/10" :aria-label="t('admin.theme_toggle')" @click="toggle">
                    <KtIcon :name="isDark ? 'sun' : 'moon'" class="text-lg group-hover:text-primary" />
                </button>

                <!-- User dropdown (Metronic kt-dropdown-menu markup) -->
                <div v-if="user" ref="userRoot" class="relative shrink-0">
                    <button type="button" class="shrink-0 cursor-pointer" :aria-expanded="userMenu" aria-haspopup="menu" @click="userMenu = !userMenu">
                        <UserAvatar :avatar="user.avatar" :name="user.name" />
                    </button>
                    <div :class="['kt-dropdown-menu absolute end-0 top-full z-50 mt-2.5 w-[260px]', { open: userMenu }]" role="menu">
                        <div class="flex items-center justify-between gap-1.5 px-2.5 py-1.5">
                            <div class="flex items-center gap-2">
                                <UserAvatar :avatar="user.avatar" :name="user.name" />
                                <span class="text-sm font-semibold leading-none text-foreground">{{ user.name }}</span>
                            </div>
                            <span class="kt-badge kt-badge-sm kt-badge-primary kt-badge-outline">{{ t('admin.panel') }}</span>
                        </div>
                        <ul class="kt-dropdown-menu-sub">
                            <li><div class="kt-dropdown-menu-separator" /></li>
                            <li>
                                <a :href="route('storefront.home')" target="_blank" rel="noopener" class="kt-dropdown-menu-link"><KtIcon name="shop" /> {{ t('admin.view_store') }}</a>
                            </li>
                            <li v-for="locale in available" :key="locale.code">
                                <a :href="locale.url" :lang="locale.code" class="kt-dropdown-menu-link">
                                    <KtIcon name="geolocation" />
                                    {{ locale.native }}
                                    <KtIcon v-if="locale.code === current" name="check" class="ms-auto text-primary" />
                                </a>
                            </li>
                            <li><div class="kt-dropdown-menu-separator" /></li>
                        </ul>
                        <div class="px-2.5 pt-1.5 pb-2.5">
                            <button type="button" class="kt-btn kt-btn-outline w-full justify-center" @click="logout">{{ t('common.logout') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <AdminSearch v-model:open="searchOpen" />
</template>
