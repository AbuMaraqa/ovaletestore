import { onMounted, ref } from 'vue';

const STORAGE_KEY = 'kt-theme';

/** Metronic light/dark mode via the `dark` class on <html>, persisted per browser. */
export function useTheme() {
    const isDark = ref(false);

    function apply(dark: boolean): void {
        isDark.value = dark;
        document.documentElement.classList.toggle('dark', dark);
        try {
            localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light');
        } catch {
            /* storage unavailable — keep in-memory only */
        }
    }

    onMounted(() => {
        let stored: string | null = null;
        try {
            stored = localStorage.getItem(STORAGE_KEY);
        } catch {
            /* ignore */
        }
        apply(stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches);
    });

    return { isDark, toggle: () => apply(!isDark.value) };
}
