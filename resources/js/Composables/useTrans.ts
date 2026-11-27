import { usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

/**
 * UI strings come from Laravel lang files (shared once per locale).
 * t('storefront.add_to_cart', { count: 2 }) — ":count" placeholders are replaced.
 * Missing keys return the key itself so gaps are visible instead of breaking the page.
 */
/**
 * Laravel's pluralization (trans_choice) for strings with "|": explicit `{n}` / `[a,b]` / `[a,*]` ranges first
 * (Arabic needs them: حقل، حقلان، حقول), otherwise "one|many".
 */
function choose(text: string, count: number): string {
    const parts = text.split('|');
    for (const part of parts) {
        const exact = part.match(/^\{(\d+)\}\s*/);
        if (exact && Number(exact[1]) === count) return part.slice(exact[0].length);
        const range = part.match(/^\[(\d+|\*),\s*(\d+|\*)\]\s*/);
        if (range) {
            const [min, max] = [range[1] === '*' ? -Infinity : Number(range[1]), range[2] === '*' ? Infinity : Number(range[2])];
            if (count >= min && count <= max) return part.slice(range[0].length);
        }
    }
    const plain = parts.map((p) => p.replace(/^(\{\d+\}|\[[^\]]+\])\s*/, ''));
    return plain[count === 1 ? 0 : Math.min(1, plain.length - 1)];
}

export function useTrans() {
    const page = usePage<SharedProps>();

    function t(key: string, replacements: Record<string, string | number> = {}): string {
        const value = key
            .split('.')
            .reduce<unknown>((node, part) => (node && typeof node === 'object' ? (node as Record<string, unknown>)[part] : undefined), page.props.translations);

        let text = typeof value === 'string' ? value : key;
        if (text.includes('|') && typeof replacements.count === 'number') text = choose(text, replacements.count);
        const names = Object.keys(replacements);
        if (!names.length) return text;
        // One pass, longest name first, like Laravel: ":to" must never eat the start of ":total".
        const pattern = new RegExp(`:(${names.sort((a, b) => b.length - a.length).join('|')})`, 'g');
        return text.replace(pattern, (_, name: string) => String(replacements[name]));
    }

    return { t };
}
