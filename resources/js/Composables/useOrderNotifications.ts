import { usePage, usePoll } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import type { SharedProps } from '@/types';

export interface AdminNotification {
    id: string;
    type: 'order.placed' | 'trader.requested';
    number: string | null;
    customer: string | null;
    total: string | null;
    read: boolean;
    time: string;
}

interface Props extends SharedProps {
    admin_notifications: { unread: number; items: AdminNotification[] } | null;
    admin_badges: { pending_orders?: number; pending_traders?: number } | null;
}

/**
 * Polls the server for new-order notifications (partial reload: only the two small props).
 * When the unread count grows, exposes the newest notification for a toast and plays a short chime.
 * Polling pauses automatically while the tab is hidden (Inertia default).
 */
export function useOrderNotifications(intervalMs = 20000) {
    const page = usePage<Props>();
    const unread = computed(() => page.props.admin_notifications?.unread ?? 0);
    const items = computed(() => page.props.admin_notifications?.items ?? []);
    const pending = computed(() => page.props.admin_badges?.pending_orders ?? 0);
    const latest = ref<AdminNotification | null>(null);

    usePoll(intervalMs, { only: ['admin_notifications', 'admin_badges'] });

    watch(unread, (now, before) => {
        if (before === undefined || now <= before) return;
        latest.value = items.value.find((n) => !n.read) ?? null;
        chime();
        setTimeout(() => (latest.value = null), 8000);
    });

    // Reflect unread orders in the tab title, e.g. "(2) لوحة التحكم".
    watch(unread, (count) => {
        document.title = document.title.replace(/^\(\d+\)\s*/, '');
        if (count > 0) document.title = `(${count}) ${document.title}`;
    }, { immediate: true });

    return { unread, items, pending, latest };
}

/** Two-tone chime via Web Audio (no asset file, CSP-friendly). Silently ignored if audio is blocked. */
function chime(): void {
    try {
        const ctx = new AudioContext();
        [880, 1320].forEach((freq, i) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0.0001, ctx.currentTime + i * 0.15);
            gain.gain.exponentialRampToValueAtTime(0.15, ctx.currentTime + i * 0.15 + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + i * 0.15 + 0.35);
            osc.connect(gain).connect(ctx.destination);
            osc.start(ctx.currentTime + i * 0.15);
            osc.stop(ctx.currentTime + i * 0.15 + 0.4);
        });
    } catch {
        /* autoplay policy / no audio */
    }
}
