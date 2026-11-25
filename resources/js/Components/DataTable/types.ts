import type { PaginationLink } from '@/types';

/** A list-screen column. `sortable` uses the column key unless `sort` names the server sort field. */
export interface Column {
    key: string;
    label: string;
    sortable?: boolean | string;
    align?: 'start' | 'center' | 'end';
    /** Hidden until the viewer turns it on in the columns menu. */
    hidden?: boolean;
    /** Always shown (cannot be turned off). */
    fixed?: boolean;
    class?: string;
}

export interface FilterOption {
    value: string | number;
    label: string;
}

export type Filter =
    | { key: string; label: string; type: 'select'; options: FilterOption[] }
    /** Two inputs bound to filter[from] / filter[until]. Only one date range per table. */
    | { key: 'date'; label: string; type: 'date_range' };

/** Laravel paginator JSON, plain (`->paginate()`) or wrapped by an API resource collection (`meta`). */
export interface TablePage<T> {
    data: T[];
    links?: PaginationLink[] | Record<string, string | null>;
    meta?: PageMeta;
    total?: number;
    from?: number | null;
    to?: number | null;
    current_page?: number;
    last_page?: number;
}

export interface PageMeta {
    total: number;
    from: number | null;
    to: number | null;
    current_page: number;
    last_page: number;
    links: PaginationLink[];
}

export interface TableState {
    filter: Record<string, string | number>;
    sort: string | null;
    per_page: number;
}
