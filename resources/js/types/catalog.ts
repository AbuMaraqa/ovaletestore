export interface CatalogFilterState {
    q: string | null;
    sort: string;
    in_stock: boolean;
    sale: boolean;
    brands: number[];
    price_min: string | null;
    price_max: string | null;
}

export interface CatalogFacets {
    categories: { id: number; name: string; url: string; count: number }[];
    brands: { id: number; name: string; count: number }[];
}

/** Serializes filter state to a clean query string (defaults omitted → canonical, shareable URLs). */
export function filtersToQuery(f: CatalogFilterState): Record<string, string | number | number[]> {
    return {
        ...(f.q ? { q: f.q } : {}),
        ...(f.sort !== 'newest' ? { sort: f.sort } : {}),
        ...(f.in_stock ? { in_stock: 1 } : {}),
        ...(f.sale ? { sale: 1 } : {}),
        ...(f.brands.length ? { brands: f.brands } : {}),
        ...(f.price_min ? { price_min: f.price_min } : {}),
        ...(f.price_max ? { price_max: f.price_max } : {}),
    };
}
