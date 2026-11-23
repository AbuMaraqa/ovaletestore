<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { onClickOutside } from '@/Composables/onClickOutside';
import { useOrderNotifications } from '@/Composables/useOrderNotifications';
import { useTrans } from '@/Composables/useTrans';

/** Header bell: unread badge, dropdown list (Metronic dropdown markup) and a toast for orders arriving live. */
const { t } = useTrans();
const { unread, items, latest } = useOrderNotifications();
const open = ref(false);
const root = ref<HTMLElement | null>(null);
onClickOutside(root, () => (open.value = false));

const openNotification = (id: string) => router.patch(route('admin.notifications.read', id));
const readAll = () => router.post(route('admin.notifications.read-all'), {}, { preserveScroll: true });
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="group kt-btn kt-btn-ghost kt-btn-icon relative size-9 rounded-full hover:bg-primary/10"
            :aria-label="`${t('admin.notifications')}: ${unread}`"
            :aria-expanded="open"
            @click="open = !open"
        >
            <KtIcon name="notification-status" :class="['text-lg group-hover:text-primary', unread && 'animate-[wiggle_1s_ease-in-out_2]']" />
            <span v-if="unread" class="kt-badge kt-badge-xs kt-badge-destructive absolute -end-0.5 -top-0.5 min-w-4 justify-center rounded-full px-1 tabular-nums">{{ unread > 99 ? '99+' : unread }}</span>
        </button>

        <div :class="['kt-dropdown-menu absolute end-0 top-full z-50 mt-2.5 w-[340px] p-0', { open }]" role="menu">
            <div class="flex items-center justify-between border-b border-border px-4 py-3">
                <span class="text-sm font-semibold text-mono">{{ t('admin.notifications') }}</span>
                <button v-if="unread" type="button" class="text-xs font-medium text-primary hover:underline" @click="readAll">{{ t('admin.mark_all_read') }}</button>
            </div>
            <ul v-if="items.length" class="max-h-[22rem] overflow-y-auto py-1">
                <li v-for="n in items" :key="n.id">
                    <button type="button" :class="['flex w-full items-start gap-3 px-4 py-3 text-start hover:bg-accent', !n.read && 'bg-primary/5']" @click="openNotification(n.id)">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-green-500/10 text-green-600"><KtIcon name="handcart" /></span>
                        <span class="min-w-0 flex-1 text-sm">
                            <span class="block font-medium text-mono">{{ t('admin.new_order_from', { name: n.customer ?? '' }) }}</span>
                            <span class="block text-xs text-muted-foreground"><span dir="ltr">{{ n.number }}</span> · <span dir="ltr">{{ n.total }}</span> · {{ n.time }}</span>
                        </span>
                        <span v-if="!n.read" class="mt-2 size-2 shrink-0 rounded-full bg-primary" />
                    </button>
                </li>
            </ul>
            <p v-else class="px-4 py-8 text-center text-sm text-muted-foreground">{{ t('admin.no_notifications') }}</p>
        </div>

        <!-- Live toast for a newly arrived order -->
        <Teleport to="body">
            <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="translate-y-4 opacity-0" leave-active-class="transition duration-200" leave-to-class="opacity-0">
                <button
                    v-if="latest"
                    type="button"
                    class="fixed bottom-6 end-6 z-[110] flex w-80 items-start gap-3 rounded-xl border border-border bg-background p-4 text-start shadow-xl"
                    role="alert"
                    @click="openNotification(latest.id)"
                >
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-green-500 text-white"><KtIcon name="handcart" /></span>
                    <span class="text-sm">
                        <span class="block font-semibold text-mono">{{ t('admin.new_order') }} <span dir="ltr">{{ latest.number }}</span></span>
                        <span class="block text-muted-foreground">{{ latest.customer }} · <span dir="ltr">{{ latest.total }}</span></span>
                    </span>
                </button>
            </Transition>
        </Teleport>
    </div>
</template>
