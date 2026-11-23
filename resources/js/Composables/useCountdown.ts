import { computed, onBeforeUnmount, onMounted, ref, watch, type Ref } from 'vue';

/** Live countdown to an ISO date (sale end). `null` once the deadline has passed or when there is none. */
export function useCountdown(target: Ref<string | null | undefined>) {
    const now = ref(Date.now());
    let timer: ReturnType<typeof setInterval> | undefined;

    const remaining = computed(() => {
        if (!target.value) return null;
        const ms = new Date(target.value).getTime() - now.value;
        if (!Number.isFinite(ms) || ms <= 0) return null;
        const s = Math.floor(ms / 1000);
        return { days: Math.floor(s / 86400), hours: Math.floor((s % 86400) / 3600), minutes: Math.floor((s % 3600) / 60), seconds: s % 60 };
    });

    const start = () => {
        stop();
        if (target.value) timer = setInterval(() => (now.value = Date.now()), 1000);
    };
    const stop = () => timer && clearInterval(timer);

    onMounted(start);
    watch(target, start);
    onBeforeUnmount(stop);

    return { remaining };
}

/** Shared flag: the product page's sticky buy bar is showing, so floating buttons move up. */
export const stickyBarVisible = ref(false);
