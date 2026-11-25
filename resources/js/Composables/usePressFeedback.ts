import { onBeforeUnmount, onMounted } from 'vue';
import { prefersReducedMotion } from '@/Composables/useMotion';

const SELECTOR = '.kt-btn, .kt-menu-link, .kt-dropdown-menu-link, [data-ripple]';

/**
 * Tactile feedback for every button: a ripple that grows from the press point (the press "squash"
 * itself is CSS, see admin.css). One delegated listener for the whole layout; positions are set
 * through CSSOM (allowed by the strict CSP, unlike style attributes). Off with reduced motion.
 */
export function usePressFeedback(): void {
    function onPointerDown(event: PointerEvent): void {
        if (event.button !== 0 || prefersReducedMotion()) return;
        const target = (event.target as Element | null)?.closest<HTMLElement>(SELECTOR);
        if (!target || target.matches(':disabled, [aria-disabled="true"]')) return;

        const rect = target.getBoundingClientRect();
        const size = Math.hypot(rect.width, rect.height) * 2;
        const ripple = document.createElement('span');
        ripple.className = 'press-ripple';
        ripple.style.width = ripple.style.height = `${size}px`;
        ripple.style.left = `${event.clientX - rect.left - size / 2}px`;
        ripple.style.top = `${event.clientY - rect.top - size / 2}px`;
        const host = document.createElement('span');
        host.className = 'press-ripple-host';
        host.setAttribute('aria-hidden', 'true');
        host.appendChild(ripple);
        target.appendChild(host);
        ripple.addEventListener('animationend', () => host.remove(), { once: true });
    }

    onMounted(() => document.addEventListener('pointerdown', onPointerDown, { passive: true }));
    onBeforeUnmount(() => document.removeEventListener('pointerdown', onPointerDown));
}
