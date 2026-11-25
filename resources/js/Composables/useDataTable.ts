import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import type { Column, PageMeta, TablePage, TableState } from '@/Components/DataTable/types';
import { useDebounce } from '@/Composables/useDebounce';

/**
 * State of an admin list screen, mirrored in the URL (`filter[...]`, `sort`, `per_page`) so the
 * server stays the source of truth and links/back button keep the view. Column choices are a
 * per-viewer convenience kept in localStorage (safe when storage is unavailable).
 */
export function useDataTable(options: { url: () => string; state: () => TableState; columns: () => Column[]; storageKey: string }) {
    const initial = options.state();
    const search = ref(String(initial.filter?.search ?? ''));
    const filters = reactive<Record<string, string>>(
        Object.fromEntries(Object.entries(initial.filter ?? {}).filter(([k]) => k !== 'search').map(([k, v]) => [k, String(v)])),
    );
    const sort = ref<string | null>(initial.sort ?? null);
    const perPage = ref<number>(initial.per_page ?? 25);
    const loading = ref(false);

    function query(): Record<string, unknown> {
        const filter: Record<string, string> = {};
        if (search.value.trim()) filter.search = search.value.trim();
        for (const [key, value] of Object.entries(filters)) if (value !== '' && value != null) filter[key] = value;

        return { ...(Object.keys(filter).length ? { filter } : {}), ...(sort.value ? { sort: sort.value } : {}), ...(perPage.value !== 25 ? { per_page: perPage.value } : {}) };
    }

    const reload = () => router.get(options.url(), query() as never, { preserveState: true, preserveScroll: true, replace: true });
    const debounced = useDebounce(reload, 350);
    watch(search, debounced);

    const offStart = router.on('start', () => (loading.value = true));
    const offFinish = router.on('finish', () => (loading.value = false));
    onBeforeUnmount(() => {
        offStart();
        offFinish();
    });

    function setFilter(key: string, value: string): void {
        if (value === '') delete filters[key];
        else filters[key] = value;
        reload();
    }

    function resetFilters(): void {
        for (const key of Object.keys(filters)) delete filters[key];
        search.value = '';
        reload();
    }

    /** Click on a header: ascending → descending → off. */
    function toggleSort(field: string): void {
        sort.value = sort.value === field ? `-${field}` : sort.value === `-${field}` ? null : field;
        reload();
    }

    function setPerPage(value: number): void {
        perPage.value = value;
        reload();
    }

    // Column visibility (per viewer, per screen).
    const storageKey = `dt:${options.storageKey}:columns`;
    const readHidden = (): string[] | null => {
        try {
            const raw = localStorage.getItem(storageKey);
            return raw ? (JSON.parse(raw) as string[]) : null;
        } catch {
            return null;
        }
    };
    const hidden = ref<string[]>(readHidden() ?? options.columns().filter((c) => c.hidden).map((c) => c.key));
    watch(hidden, (value) => {
        try {
            localStorage.setItem(storageKey, JSON.stringify(value));
        } catch {
            /* storage unavailable: keep the choice for this visit only */
        }
    });
    const visibleColumns = computed(() => options.columns().filter((c) => c.fixed || !hidden.value.includes(c.key)));
    const toggleColumn = (key: string) => (hidden.value = hidden.value.includes(key) ? hidden.value.filter((k) => k !== key) : [...hidden.value, key]);

    const activeFilterCount = computed(() => Object.keys(filters).length);

    return { search, filters, sort, perPage, loading, reload, setFilter, resetFilters, toggleSort, setPerPage, hidden, visibleColumns, toggleColumn, activeFilterCount };
}

/** Normalizes both paginator JSON shapes. */
export function pageMeta<T>(page: TablePage<T>): PageMeta {
    if (page.meta) return page.meta;
    return {
        total: page.total ?? page.data.length,
        from: page.from ?? null,
        to: page.to ?? null,
        current_page: page.current_page ?? 1,
        last_page: page.last_page ?? 1,
        links: Array.isArray(page.links) ? page.links : [],
    };
}
