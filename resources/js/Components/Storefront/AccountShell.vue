<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import { useTrans } from '@/Composables/useTrans';
import type { SharedProps } from '@/types';

/** Account area frame: profile card + navigation + content. */
defineProps<{ title: string }>();
const page = usePage<SharedProps>();
const { t } = useTrans();

const links = [
    { label: 'account.overview', route: 'account.dashboard' },
    { label: 'account.my_orders', route: 'account.orders' },
];
</script>

<template>
    <div class="container-store py-10">
        <div class="grid gap-8 lg:grid-cols-[16rem_1fr]">
            <aside class="space-y-4">
                <div v-if="page.props.auth.user" class="flex items-center gap-3 rounded-3xl bg-white p-4 ring-1 ring-stone-200/70">
                    <UserAvatar :avatar="page.props.auth.user.avatar" :name="page.props.auth.user.name" size="size-12" :ring="false" />
                    <div class="min-w-0">
                        <p class="text-xs text-stone-500">{{ t('account.hello') }}</p>
                        <p class="truncate font-bold text-cocoa">{{ page.props.auth.user.name }}</p>
                    </div>
                </div>
                <nav class="rounded-3xl bg-white p-2 ring-1 ring-stone-200/70" aria-label="Account">
                    <Link
                        v-for="link in links"
                        :key="link.route"
                        :href="route(link.route)"
                        :class="['block rounded-2xl px-4 py-3 text-sm font-semibold transition', route().current(link.route + '*') ? 'bg-cocoa text-white' : 'text-stone-700 hover:bg-stone-100']"
                    >
                        {{ t(link.label) }}
                    </Link>
                    <a v-if="page.props.auth.user?.admin_url" :href="page.props.auth.user.admin_url" class="block rounded-2xl px-4 py-3 text-sm font-semibold text-brand-700 hover:bg-brand-50">{{ t('account.control_panel') }}</a>
                    <button type="button" class="block w-full rounded-2xl px-4 py-3 text-start text-sm font-semibold text-red-600 hover:bg-red-50" @click="router.post(route('logout'))">{{ t('common.logout') }}</button>
                </nav>
            </aside>
            <section class="min-w-0">
                <h1 class="mb-6 text-2xl font-extrabold tracking-tight text-cocoa sm:text-3xl">{{ title }}</h1>
                <slot />
            </section>
        </div>
    </div>
</template>
