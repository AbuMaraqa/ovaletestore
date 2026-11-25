import { usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import type { SharedProps } from '@/types';

export interface ActivityChange {
    field: string;
    old: string | boolean | null;
    new: string | boolean | null;
}

export interface ActivityEntry {
    id: number;
    log: string | null;
    event: string;
    subject: string | null;
    child: string | null;
    label: string | null;
    locale: string | null;
    url: string | null;
    causer: { id: number; name: string; avatar: { url: string | null; initials: string; color: string } | null } | null;
    changes: ActivityChange[];
    details: Record<string, unknown>;
    ip: string | null;
    agent: string | null;
    created_at: string;
    date: string;
    ago: string;
}

/** The side panel with one record's history (rendered once by <ActivityPanel> in the admin layout). */
const state = reactive<{ open: boolean; loading: boolean; label: string; entries: ActivityEntry[]; error: boolean }>({
    open: false,
    loading: false,
    label: '',
    entries: [],
    error: false,
});

export function useActivityPanel() {
    const page = usePage<SharedProps>();
    const allowed = () => page.props.auth.permissions.includes('activity.view');

    async function open(target: { type: string; id: number; label: string }): Promise<void> {
        Object.assign(state, { open: true, loading: true, label: target.label, entries: [], error: false });
        try {
            const response = await fetch(route('admin.activity.record', { type: target.type, id: target.id }), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error(String(response.status));
            state.entries = ((await response.json()) as { data: ActivityEntry[] }).data;
        } catch {
            state.error = true;
        } finally {
            state.loading = false;
        }
    }

    const close = () => (state.open = false);

    return { state, open, close, allowed };
}
