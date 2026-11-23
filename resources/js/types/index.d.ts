import type { route as ziggyRoute } from 'ziggy-js';

export interface Image {
    id: number;
    alt: string;
    src: string;
    srcset: string | null;
    avif_srcset: string | null;
    large: string;
}

export interface LocaleOption {
    code: string;
    native: string;
    url: string;
}

export interface Avatar {
    url: string | null;
    initials: string;
    color: string;
}

export interface AuthUser {
    id: number;
    name: string;
    avatar: Avatar;
    is_staff: boolean;
}

export interface SeoAlternate {
    hreflang: string;
    href: string;
}

export interface Seo {
    title: string;
    description: string | null;
    canonical: string;
    robots: string;
    alternates: SeoAlternate[];
    og: { type: string; site_name: string; locale: string; image: string | null };
    json_ld: Record<string, unknown>[];
}

export interface SharedProps {
    auth: { user: AuthUser | null; permissions: string[] };
    locale: { current: 'ar' | 'en' | 'he'; dir: 'rtl' | 'ltr'; available: LocaleOption[] };
    site: { name: string; tagline: string | null; logo: string | null; whatsapp: string | null; phone: string | null; social: Record<string, string>; currency: string };
    cart_count: number;
    flash: { success: string | null; error: string | null };
    translations: Record<string, Record<string, unknown>>;
    seo?: Seo;
    [key: string]: unknown;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    meta?: { current_page: number; last_page: number; total: number; from: number | null; to: number | null; links: PaginationLink[] };
    current_page?: number;
    last_page?: number;
    total?: number;
}

export interface ProductCard {
    id: number;
    name: string;
    url: string;
    image: Image | null;
    is_variable: boolean;
    price: string;
    price_max: string | null;
    regular_price: string | null;
    on_sale: boolean;
    in_stock: boolean;
    default_variant_id: number | null;
    category: string | null;
}

export interface CategoryCard {
    id: number;
    name: string;
    url: string;
    image: Image | null;
    products_count?: number;
}

declare global {
    // Provided at runtime by Ziggy's @routes directive (window.route).
    const route: typeof ziggyRoute;
}

declare module '@inertiajs/core' {
    interface PageProps extends SharedProps {}
}

declare module 'vue' {
    interface ComponentCustomProperties {
        route: typeof ziggyRoute;
    }
}
