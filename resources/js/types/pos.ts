export interface PosCompany {
    id: number;
    name: string;
    contact_name: string | null;
    phone: string | null;
    city: string | null;
    address?: string | null;
}

export interface PosOrderSummary {
    id: number;
    number: string;
    company: string;
    status: string;
    items_count: number;
    total: string;
    date: string;
}

/** One purchasable variant in the POS catalog. `price` is in minor units (agorot). */
export interface PosItem {
    id: number;
    name: string;
    variant: string | null;
    sku: string | null;
    price: number;
    price_formatted: string;
    on_sale: boolean;
    available: boolean;
    max: number | null;
    image: string | null;
    categories: number[];
}

export interface PosCatalog {
    categories: { id: number; name: string }[];
    items: PosItem[];
}
