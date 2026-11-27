import type { Column, Filter } from '@/Components/DataTable/types';

/** A cell definition from App\Tables\Column (server). Values are read from row fields. */
export interface ColumnDef extends Column {
    type: 'text' | 'badge' | 'boolean' | 'money' | 'number' | 'date';
    field: string;
    href?: string;
    sub?: string;
    avatar?: string;
    image?: string;
    title?: string;
    chips?: { when: string; label: string; variant?: string; count?: string }[];
    badges?: Record<string, { label: string; variant: string }>;
    labels?: { true: string; false: string };
    ltr?: boolean;
    mono?: boolean;
    strong?: boolean;
    muted?: boolean;
    truncate?: boolean;
}

export interface BulkDef {
    action: string;
    label: string;
    confirm: boolean;
    variant: 'outline' | 'destructive';
    /** Document actions open this URL with ?ids=… (new tab) instead of posting. */
    href: string | null;
}

/** One extra row button (e.g. "view in store", "make default"). */
export interface ExtraAction {
    label: string;
    icon: string;
    href: string;
    /** 'get' opens the link; 'patch'/'post'/'delete' send an Inertia request. */
    method?: 'get' | 'post' | 'patch' | 'delete';
    target?: '_blank';
    confirm?: { title: string; message: string; confirm: string };
}

/** The table definition from App\Tables\Table::props() (`table` prop). */
export interface TableDef {
    key: string;
    title: string;
    url: string;
    search: string | null;
    columns: ColumnDef[];
    filters: Filter[];
    actions: { view?: string; edit?: string; delete?: string; label?: string; activity?: string; extra?: string };
    bulk: BulkDef[];
    /** Shared bulk endpoint of this table (POST {action, ids}). */
    bulk_url: string;
    export_url: string | null;
}
