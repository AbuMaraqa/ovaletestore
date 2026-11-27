import { router, type InertiaForm } from '@inertiajs/vue3';
import { computed, inject, nextTick, onBeforeUnmount, onMounted, provide, reactive, type ComputedRef, type InjectionKey } from 'vue';
import { useConfirm } from '@/Composables/useConfirm';
import { prefersReducedMotion } from '@/Composables/useMotion';
import { useTrans } from '@/Composables/useTrans';

/**
 * The admin form system (Components/Forms/*), one instance per page form:
 *  - fields register their error key, control id and label → <ErrorSummary> links jump to the field;
 *  - containers that hide fields (locale tabs, collapsible sections, variant rows) register a "revealer", so a jump
 *    first opens the right tab/section, then scrolls to and focuses the control;
 *  - an error is cleared as soon as its field is edited;
 *  - unsaved changes are guarded (Inertia visits + closing the tab), Ctrl/Cmd+S saves.
 */
export interface FieldEntry {
    key: string;
    id: string;
    label: string;
    context?: string; // e.g. the locale or variant name shown in the summary
}
type Revealer = (key: string) => boolean;

export interface FormKit {
    errors: ComputedRef<Record<string, string>>;
    fields: Map<string, FieldEntry>;
    register: (entry: FieldEntry) => () => void;
    addRevealer: (fn: Revealer) => () => void;
    clear: (key: string) => void;
    count: (prefixes: string[]) => number;
    reveal: (key: string) => Promise<void>;
}

const KIT: InjectionKey<FormKit> = Symbol('formKit');

export function useFormKitContext(): FormKit | null {
    return inject(KIT, null);
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function useFormKit(form: InertiaForm<any>, options: { submit: () => void; guard?: boolean }) {
    const { t } = useTrans();
    const { confirm } = useConfirm();
    const fields = reactive(new Map<string, FieldEntry>()) as Map<string, FieldEntry>;
    const revealers = new Set<Revealer>();
    const errors = computed(() => form.errors as Record<string, string>);

    function controlFor(key: string): HTMLElement | null {
        const entry = fields.get(key);
        const byId = entry ? document.getElementById(entry.id) : null;
        return byId ?? document.querySelector<HTMLElement>(`[data-field-key="${CSS.escape(key)}"] :is(input, select, textarea, [contenteditable="true"], button)`);
    }

    async function reveal(key: string): Promise<void> {
        revealers.forEach((fn) => fn(key));
        await nextTick();
        const control = controlFor(key);
        if (!control) return;
        control.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'center' });
        control.focus({ preventScroll: true });
        const item = control.closest<HTMLElement>('[data-field-key]');
        item?.classList.add('field-flash');
        window.setTimeout(() => item?.classList.remove('field-flash'), 700);
    }

    const kit: FormKit = {
        errors,
        fields,
        register(entry) {
            fields.set(entry.key, entry);
            return () => {
                if (fields.get(entry.key)?.id === entry.id) fields.delete(entry.key);
            };
        },
        addRevealer(fn) {
            revealers.add(fn);
            return () => revealers.delete(fn);
        },
        clear(key) {
            if (key && errors.value[key]) form.clearErrors(key);
        },
        count: (prefixes) => Object.keys(errors.value).filter((k) => prefixes.some((p) => k === p || k.startsWith(`${p}.`))).length,
        reveal,
    };
    provide(KIT, kit);

    /** Call from the visit's onError: open + focus the first invalid field (the summary announces the rest). */
    async function focusFirstError(): Promise<void> {
        const first = Object.keys(errors.value)[0];
        if (first) await reveal(first);
    }

    // Unsaved changes: Inertia GET visits ask first; closing/reloading the tab gets the browser prompt.
    let leaving = false;
    function onBeforeUnload(event: BeforeUnloadEvent): void {
        if (form.isDirty && !form.processing && !leaving) event.preventDefault();
    }
    const offBefore = router.on('before', (event) => {
        const visit = event.detail.visit;
        if (!options.guard || leaving || form.processing || !form.isDirty || visit.method !== 'get' || visit.preserveState === true) return;
        event.preventDefault();
        void confirm({ title: t('forms.unsaved_title'), message: t('forms.unsaved_message'), confirmLabel: t('forms.leave') }).then((ok) => {
            if (!ok) return;
            leaving = true;
            router.visit(visit.url, { method: visit.method, preserveScroll: visit.preserveScroll });
        });
    });

    function onKey(event: KeyboardEvent): void {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            if (!form.processing) options.submit();
        }
    }

    onMounted(() => {
        window.addEventListener('keydown', onKey);
        if (options.guard) window.addEventListener('beforeunload', onBeforeUnload);
    });
    onBeforeUnmount(() => {
        offBefore();
        window.removeEventListener('keydown', onKey);
        window.removeEventListener('beforeunload', onBeforeUnload);
    });

    return { kit, focusFirstError, reveal, errors, errorCount: computed(() => Object.keys(errors.value).length) };
}
