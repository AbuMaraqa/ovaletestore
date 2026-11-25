import { reactive } from 'vue';

interface ConfirmRequest {
    open: boolean;
    title: string;
    message: string;
    confirmLabel: string;
    danger: boolean;
    resolve: ((ok: boolean) => void) | null;
}

/** One app-wide confirmation dialog (rendered by <ConfirmHost> in the admin layout). */
const state = reactive<ConfirmRequest>({ open: false, title: '', message: '', confirmLabel: '', danger: true, resolve: null });

export function useConfirm() {
    function confirm(options: { title: string; message?: string; confirmLabel: string; danger?: boolean }): Promise<boolean> {
        state.resolve?.(false);
        return new Promise((resolve) => {
            Object.assign(state, { open: true, message: '', danger: true, ...options, resolve });
        });
    }

    function settle(ok: boolean): void {
        state.resolve?.(ok);
        Object.assign(state, { open: false, resolve: null });
    }

    return { state, confirm, settle };
}
