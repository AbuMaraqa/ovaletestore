<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { adminMenu } from '@/Components/Navigation/adminMenu';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

defineProps<{ mobileOpen: boolean }>();
const emit = defineEmits<{ close: []; toggleCollapse: [] }>();

const page = usePage<SharedProps>();
const { t } = useTrans();

const sections = computed(() => {
    const permissions = new Set(page.props.auth.permissions);
    return adminMenu
        .map((section) => ({ ...section, items: section.items.filter((item) => permissions.has(item.permission)) }))
        .filter((section) => section.items.length > 0);
});

const isActive = (match: string) => route().current(match);
</script>

<template>
    <div v-if="mobileOpen" class="fixed inset-0 z-20 bg-black/40 lg:hidden" @click="emit('close')" />
    <aside
        :class="[
            'kt-sidebar fixed top-0 bottom-0 z-30 flex flex-col items-stretch border-e border-e-border bg-background transition-transform lg:z-20 lg:translate-x-0',
            mobileOpen ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full lg:rtl:translate-x-0',
        ]"
    >
        <div class="kt-sidebar-header relative flex h-[70px] shrink-0 items-center justify-between px-6">
            <Link :href="route('admin.dashboard')" class="default-logo flex items-center gap-2 text-lg font-bold text-mono">
                <span class="flex size-8 items-center justify-center rounded-lg bg-primary text-sm text-primary-foreground">O</span>
                {{ page.props.site.name }}
            </Link>
            <span class="small-logo hidden size-8 items-center justify-center rounded-lg bg-primary text-sm font-bold text-primary-foreground">O</span>
            <button
                type="button"
                class="kt-btn kt-btn-outline kt-btn-icon absolute start-full top-1/2 hidden size-[30px] -translate-x-1/2 -translate-y-1/2 lg:inline-flex rtl:translate-x-1/2"
                :aria-label="t('admin.collapse_sidebar')"
                @click="emit('toggleCollapse')"
            >
                <KtIcon name="black-left-line" class="rtl:rotate-180" />
            </button>
        </div>

        <nav class="kt-sidebar-content flex grow overflow-y-auto py-5 pe-2" aria-label="Admin">
            <div class="kt-menu flex grow flex-col gap-1 ps-3 pe-1 lg:ps-5 lg:pe-3">
                <template v-for="(section, s) in sections" :key="s">
                    <div v-if="section.heading" class="kt-menu-item pt-3 pb-1">
                        <span class="kt-menu-heading ps-2.5 text-xs font-medium uppercase text-muted-foreground">{{ t(section.heading) }}</span>
                    </div>
                    <div v-for="item in section.items" :key="item.route" class="kt-menu-item" :class="{ active: isActive(item.match) }">
                        <Link
                            :href="route(item.route, item.params ?? {})"
                            :class="[
                                'kt-menu-link flex grow items-center gap-2.5 rounded-lg border border-transparent px-2.5 py-2 text-sm font-medium transition',
                                isActive(item.match) ? 'bg-accent/60 text-primary' : 'text-foreground hover:bg-accent/60 hover:text-primary',
                            ]"
                            :aria-current="isActive(item.match) ? 'page' : undefined"
                            @click="emit('close')"
                        >
                            <span class="kt-menu-icon w-5 text-lg text-muted-foreground"><KtIcon :name="item.icon" /></span>
                            <span class="kt-menu-title">{{ t(item.label) }}</span>
                        </Link>
                    </div>
                </template>
            </div>
        </nav>
    </aside>
</template>
