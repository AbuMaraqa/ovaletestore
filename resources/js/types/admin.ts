export type Locale = 'ar' | 'en' | 'he';

export interface VariantForm {
    id?: number;
    term_ids: number[];
    sku: string | null;
    regular_price: string | number | null;
    sale_price: string | number | null;
    sale_starts_at: string | null;
    sale_ends_at: string | null;
    manage_stock: boolean;
    stock_quantity: number | null;
    stock_status: 'in_stock' | 'out_of_stock' | 'on_backorder';
    weight_grams: number | null;
    length_mm: number | null;
    width_mm: number | null;
    height_mm: number | null;
    is_active: boolean;
    is_default: boolean;
}

export interface ProductAttributeForm {
    attribute_id: number;
    term_ids: number[];
    used_for_variations: boolean;
    is_visible: boolean;
}

export interface ProductTranslationForm {
    name: string;
    slug: string;
    short_description: string;
    description: string;
    meta_title: string;
    meta_description: string;
}

export interface ProductForm {
    id?: number;
    type: 'simple' | 'variable';
    status: 'draft' | 'published' | 'archived';
    brand_id: number | null;
    is_featured: boolean;
    published_at: string | null;
    translations: Record<Locale, ProductTranslationForm>;
    category_ids: number[];
    primary_category_id: number | null;
    attributes: ProductAttributeForm[];
    variants: VariantForm[];
    media?: { id: number; url: string; name: string; size: string }[];
    view_url?: string | null;
}

export interface AttributeOption {
    id: number;
    name: string;
    terms: { id: number; name: string }[];
}

export function emptyVariant(termIds: number[] = []): VariantForm {
    return {
        term_ids: termIds,
        sku: null,
        regular_price: null,
        sale_price: null,
        sale_starts_at: null,
        sale_ends_at: null,
        manage_stock: false,
        stock_quantity: null,
        stock_status: 'in_stock',
        weight_grams: null,
        length_mm: null,
        width_mm: null,
        height_mm: null,
        is_active: true,
        is_default: false,
    };
}
