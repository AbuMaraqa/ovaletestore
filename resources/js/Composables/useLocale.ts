import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { SharedProps } from '@/types';

export function useLocale() {
    const page = usePage<SharedProps>();

    const current = computed(() => page.props.locale.current);
    const isRtl = computed(() => page.props.locale.dir === 'rtl');
    const available = computed(() => page.props.locale.available);

    /** Full page load on purpose: lang/dir, fonts and the translation bundle all change. */
    function switchTo(code: string): void {
        const target = available.value.find((l) => l.code === code);
        if (target && code !== current.value) window.location.href = target.url;
    }

    return { current, isRtl, available, switchTo };
}
