import { computed, reactive } from 'vue';

export interface VariantOption {
    id: number;
    term_ids: number[];
    purchasable: boolean;
    is_default: boolean;
}

export interface VariationAttribute {
    id: number;
    used_for_variations: boolean;
    terms: { id: number }[];
}

/**
 * Pure selection logic (no pricing rules — prices/stock come from the server per variant).
 * A term is selectable when at least one purchasable variant contains it together with the other current selections.
 */
export function useVariantSelection<V extends VariantOption>(attributes: VariationAttribute[], variants: V[]) {
    const variationAttributes = attributes.filter((a) => a.used_for_variations);
    const selected = reactive<Record<number, number | null>>({});

    const initial = variants.find((v) => v.is_default && v.purchasable) ?? variants.find((v) => v.purchasable) ?? variants[0];
    for (const attribute of variationAttributes) {
        selected[attribute.id] = attribute.terms.find((term) => initial?.term_ids.includes(term.id))?.id ?? null;
    }

    const selectedIds = computed(() => Object.values(selected).filter((id): id is number => id !== null));

    const variant = computed<V | null>(() => {
        if (variationAttributes.length === 0) return variants[0] ?? null;
        if (selectedIds.value.length !== variationAttributes.length) return null;
        return variants.find((v) => selectedIds.value.every((id) => v.term_ids.includes(id))) ?? null;
    });

    function isAvailable(attributeId: number, termId: number): boolean {
        const others = variationAttributes
            .filter((a) => a.id !== attributeId)
            .map((a) => selected[a.id])
            .filter((id): id is number => id !== null && id !== undefined);

        return variants.some((v) => v.purchasable && v.term_ids.includes(termId) && others.every((id) => v.term_ids.includes(id)));
    }

    function select(attributeId: number, termId: number): void {
        selected[attributeId] = selected[attributeId] === termId ? null : termId;
    }

    return { variationAttributes, selected, variant, isAvailable, select };
}
