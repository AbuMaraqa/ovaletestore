import { computed, reactive, watch } from 'vue';
import type { PosItem } from '@/types/pos';

/**
 * Order being built on the POS: variant id → quantity, kept as a per-company draft on the device
 * (survives an accidental refresh). Totals here are a preview; the server recomputes on submit.
 */
export function usePosOrder(companyId: number, items: PosItem[], currency: string, locale: string) {
    const storageKey = `pos-draft-${companyId}`;
    const byId = new Map(items.map((item) => [item.id, item]));
    const quantities = reactive(new Map<number, number>());

    try {
        const saved = JSON.parse(localStorage.getItem(storageKey) ?? '[]') as [number, number][];
        for (const [id, qty] of saved) {
            const item = byId.get(id);
            if (item?.available && qty > 0) quantities.set(id, clamp(item, qty));
        }
    } catch {
        // storage unavailable (private mode) — drafts are a convenience only
    }

    watch(
        quantities,
        () => {
            try {
                if (quantities.size) localStorage.setItem(storageKey, JSON.stringify([...quantities.entries()]));
                else localStorage.removeItem(storageKey);
            } catch {
                /* ignore */
            }
        },
        { deep: true },
    );

    function clamp(item: PosItem, qty: number): number {
        const max = item.max ?? 9999;
        return Math.max(0, Math.min(Math.floor(qty), max, 9999));
    }

    function set(id: number, qty: number): void {
        const item = byId.get(id);
        if (!item || !item.available) return;
        const next = clamp(item, Number.isFinite(qty) ? qty : 0);
        if (next > 0) quantities.set(id, next);
        else quantities.delete(id);
    }

    const add = (id: number, step = 1) => set(id, (quantities.get(id) ?? 0) + step);
    const clear = () => quantities.clear();
    const atMax = (id: number) => {
        const item = byId.get(id);
        return item?.max != null && (quantities.get(id) ?? 0) >= item.max;
    };

    const lines = computed(() =>
        [...quantities.entries()]
            .map(([id, quantity]) => ({ item: byId.get(id)!, quantity }))
            .filter((line) => line.item),
    );
    const totalMinor = computed(() => lines.value.reduce((sum, line) => sum + line.item.price * line.quantity, 0));
    const unitCount = computed(() => lines.value.reduce((sum, line) => sum + line.quantity, 0));

    const formatter = new Intl.NumberFormat(locale, { style: 'currency', currency });
    const format = (minor: number) => formatter.format(minor / 100);

    const payload = () => lines.value.map((line) => ({ variant_id: line.item.id, quantity: line.quantity }));

    return { quantities, lines, totalMinor, unitCount, set, add, clear, atMax, format, payload };
}
