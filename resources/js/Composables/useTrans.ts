import { usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

/**
 * UI strings come from Laravel lang files (shared once per locale).
 * t('storefront.add_to_cart', { count: 2 }) — ":count" placeholders are replaced.
 * Missing keys return the key itself so gaps are visible instead of breaking the page.
 */
export function useTrans() {
    const page = usePage<SharedProps>();

    function t(key: string, replacements: Record<string, string | number> = {}): string {
        const value = key
            .split('.')
            .reduce<unknown>((node, part) => (node && typeof node === 'object' ? (node as Record<string, unknown>)[part] : undefined), page.props.translations);

        const text = typeof value === 'string' ? value : key;
        const names = Object.keys(replacements);
        if (!names.length) return text;
        // One pass, longest name first, like Laravel: ":to" must never eat the start of ":total".
        const pattern = new RegExp(`:(${names.sort((a, b) => b.length - a.length).join('|')})`, 'g');
        return text.replace(pattern, (_, name: string) => String(replacements[name]));
    }

    return { t };
}
