import { nextTick, onBeforeUnmount, watch, type Ref } from 'vue';

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * Dialog keyboard contract: while `active`, focus moves into `container` (the `initial` element or the
 * first focusable one), Tab/Shift+Tab cycle inside it, Esc calls `onEscape`, and on close focus goes
 * back to whatever opened the dialog.
 */
export function useFocusTrap(container: Ref<HTMLElement | null>, active: () => boolean, options: { onEscape: () => void; initial?: () => HTMLElement | null }) {
    let opener: HTMLElement | null = null;

    function onKeydown(event: KeyboardEvent): void {
        if (event.key === 'Escape') {
            event.preventDefault();
            options.onEscape();
            return;
        }
        if (event.key !== 'Tab' || !container.value) return;

        const items = [...container.value.querySelectorAll<HTMLElement>(FOCUSABLE)].filter((el) => el.offsetParent !== null);
        if (!items.length) return;
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    watch(active, async (isActive) => {
        if (isActive) {
            opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            document.addEventListener('keydown', onKeydown);
            await nextTick();
            (options.initial?.() ?? container.value?.querySelector<HTMLElement>(FOCUSABLE) ?? container.value)?.focus();
        } else {
            document.removeEventListener('keydown', onKeydown);
            opener?.focus();
            opener = null;
        }
    });

    onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
}
