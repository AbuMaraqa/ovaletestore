import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { CurrencyOption, SharedProps } from '@/types';

/** The storefront currency switcher: options come from the server; the choice is kept in the session. */
export function useCurrency() {
    const page = usePage<SharedProps>();
    const options = computed<CurrencyOption[]>(() => page.props.currencies ?? []);
    const current = computed(() => page.props.site.currency);

    const choose = (code: string) => {
        if (code === current.value) return;
        router.post(route('storefront.currency'), { code }, { preserveScroll: true });
    };

    return { options, current, choose };
}
