import type { Image } from '@/types';

export interface CartItem {
    id: number;
    name: string;
    url: string;
    variant_label: string | null;
    image: Image | null;
    quantity: number;
    max_quantity: number | null;
    unit_price: string;
    line_total: string;
    available: boolean;
}

export interface CartPayload {
    items: CartItem[];
    item_count: number;
    subtotal: string;
    shipping_total: string | null;
    total: string;
    meets_minimum: boolean;
}
