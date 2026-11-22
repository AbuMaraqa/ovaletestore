<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { adminMenu, type MenuItem, type MenuLink } from '@/Components/Navigation/adminMenu';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

/** Port of the Metronic demo1 sidebar; accordion/active state driven by Vue via Metronic's `.show`/`.active` classes. */
defineProps<{ mobileOpen: boolean }>();
const emit = defineEmits<{ close: []; toggleCollapse: [] }>();

const page = usePage<SharedProps>();
const { t } = useTrans();

const isLinkActive = (link: MenuLink) =>
    route().current(link.match) && (!link.params || Object.entries(link.params).every(([k, v]) => route().params[k] === v));
const isItemActive = (item: MenuItem) => (item.children ? item.children.some(isLinkActive) : isLinkActive(item as MenuLink));

const sections = computed(() => {
    const allowed = new Set(page.props.auth.permissions);
    return adminMenu
        .map((section) => ({
            ...section,
            items: section.items
                .map((item) => (item.children ? { ...item, children: item.children.filter((c) => allowed.has(c.permission)) } : item))
                .filter((item) => (item.children ? item.children.length > 0 : allowed.has(item.permission!))),
        }))
        .filter((section) => section.items.length > 0);
});

const open = reactive<Record<string, boolean>>({});
const isOpen = (item: MenuItem) => open[item.label] ?? isItemActive(item);
const toggle = (item: MenuItem) => (open[item.label] = !isOpen(item));
</script>

<template>
    <div v-if="mobileOpen" class="kt-drawer-backdrop fixed inset-0 z-20 bg-black/30 backdrop-blur-[2px] lg:hidden" @click="emit('close')" />
    <aside
        id="sidebar"
        :class="[
            'kt-sidebar fixed top-0 bottom-0 z-30 flex shrink-0 flex-col items-stretch border-e border-e-border bg-background transition-transform duration-300 lg:z-20 lg:translate-x-0',
            mobileOpen ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full lg:rtl:translate-x-0',
        ]"
    >
        <div id="sidebar_header" class="kt-sidebar-header relative flex shrink-0 items-center justify-between px-3 lg:px-6">
            <Link :href="route('admin.dashboard')" class="flex items-center gap-2.5">
                <img v-if="page.props.site.logo" :src="page.props.site.logo" alt="" class="size-9 shrink-0 rounded-lg object-contain" width="36" height="36" />
                <span class="default-logo text-base font-bold text-mono">{{ page.props.site.name }}</span>
            </Link>
            <button
                id="sidebar_toggle"
                type="button"
                class="kt-btn kt-btn-outline kt-btn-icon absolute start-full top-2/4 hidden size-[30px] -translate-x-2/4 -translate-y-2/4 lg:inline-flex rtl:translate-x-2/4"
                :aria-label="t('admin.collapse_sidebar')"
                @click="emit('toggleCollapse')"
            >
                <KtIcon name="black-left-line" class="transition-all duration-300 rtl:rotate-180" />
            </button>
        </div>

        <div id="sidebar_content" class="kt-sidebar-content flex shrink-0 grow py-5 pe-2">
            <nav class="kt-scrollable-y-hover flex shrink-0 grow overflow-y-auto ps-2 pe-1 lg:ps-5 lg:pe-3" aria-label="Admin" data-lenis-prevent>
                <div class="kt-menu flex grow flex-col gap-1">
                    <template v-for="(section, s) in sections" :key="s">
                        <div v-if="section.heading" class="kt-menu-item pt-2.25 pb-px">
                            <span class="kt-menu-heading ps-[10px] pe-[10px] text-xs font-medium uppercase text-muted-foreground">{{ t(section.heading) }}</span>
                        </div>

                        <template v-for="item in section.items" :key="item.label">
                            <!-- Accordion group -->
                            <div v-if="item.children" :class="['kt-menu-item', { show: isOpen(item), here: isItemActive(item) }]">
                                <button
                                    type="button"
                                    class="kt-menu-link flex grow cursor-pointer items-center gap-[10px] border border-transparent py-[6px] ps-[10px] pe-[10px]"
                                    :aria-expanded="isOpen(item)"
                                    @click="toggle(item)"
                                >
                                    <span class="kt-menu-icon w-[20px] items-start text-muted-foreground"><KtIcon :name="item.icon" class="text-lg" /></span>
                                    <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-here:text-primary kt-menu-link-hover:!text-primary">{{ t(item.label) }}</span>
                                    <span class="kt-menu-arrow ms-auto w-[20px] shrink-0 justify-end text-muted-foreground">
                                        <KtIcon :name="isOpen(item) ? 'minus' : 'plus'" class="text-[11px]" />
                                    </span>
                                </button>
                                <div class="kt-menu-accordion relative gap-1 ps-[10px] before:absolute before:start-[20px] before:top-0 before:bottom-0 before:border-s before:border-border">
                                    <div v-for="child in item.children" :key="child.label + JSON.stringify(child.params ?? {})" :class="['kt-menu-item', { active: isLinkActive(child) }]">
                                        <Link
                                            :href="route(child.route, child.params ?? {})"
                                            class="kt-menu-link grow items-center gap-[14px] border border-transparent py-[8px] ps-[10px] pe-[10px] kt-menu-item-active:rounded-lg kt-menu-item-active:border-border kt-menu-item-active:bg-background hover:rounded-lg hover:bg-accent/60"
                                            :aria-current="isLinkActive(child) ? 'page' : undefined"
                                            @click="emit('close')"
                                        >
                                            <span class="kt-menu-bullet relative -start-[3px] flex w-[6px] before:absolute before:top-0 before:size-[6px] before:-translate-y-1/2 before:rounded-full rtl:start-0 rtl:before:translate-x-1/2 kt-menu-item-active:before:bg-primary kt-menu-item-hover:before:bg-primary" />
                                            <span class="kt-menu-title text-2sm font-normal text-foreground kt-menu-item-active:font-semibold kt-menu-item-active:text-primary kt-menu-link-hover:!text-primary">{{ t(child.label) }}</span>
                                        </Link>
                                    </div>
                                </div>
                            </div>

                            <!-- Single link -->
                            <div v-else :class="['kt-menu-item', { active: isItemActive(item) }]">
                                <Link
                                    :href="route(item.route!, item.params ?? {})"
                                    class="kt-menu-link flex grow items-center gap-[10px] border border-transparent py-[6px] ps-[10px] pe-[10px] kt-menu-item-active:rounded-lg kt-menu-item-active:bg-accent/60 hover:rounded-lg hover:bg-accent/60"
                                    :aria-current="isItemActive(item) ? 'page' : undefined"
                                    @click="emit('close')"
                                >
                                    <span class="kt-menu-icon w-[20px] items-start text-muted-foreground kt-menu-item-active:text-primary"><KtIcon :name="item.icon" class="text-lg" /></span>
                                    <span class="kt-menu-title text-sm font-medium text-foreground kt-menu-item-active:text-primary kt-menu-link-hover:!text-primary">{{ t(item.label) }}</span>
                                </Link>
                            </div>
                        </template>
                    </template>
                </div>
            </nav>
        </div>
    </aside>
</template>
