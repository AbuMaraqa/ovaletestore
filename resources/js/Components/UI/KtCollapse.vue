<script setup lang="ts">
/**
 * Smooth open/close of a block (accordions, sub-menus, expandable rows): animates the real height like
 * Metronic's own accordion (`height .3s ease`), instead of popping in with display:none.
 * Styles are set through the CSSOM (CSP-safe). Reduced motion: no animation.
 */
defineOptions({ inheritAttrs: false });
const props = withDefaults(defineProps<{ open: boolean; duration?: number; tag?: string }>(), { duration: 300, tag: 'div' });

const reduced = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function setup(el: Element): HTMLElement {
    const node = el as HTMLElement;
    node.style.overflow = 'hidden';
    node.style.transition = reduced() ? 'none' : `height ${props.duration}ms cubic-bezier(0.4, 0, 0.2, 1), opacity ${props.duration}ms ease`;
    return node;
}

function enter(el: Element, done: () => void): void {
    const node = setup(el);
    node.style.height = '0px';
    node.style.opacity = '0';
    requestAnimationFrame(() => {
        node.style.height = `${node.scrollHeight}px`;
        node.style.opacity = '1';
        finish(node, done);
    });
}

function leave(el: Element, done: () => void): void {
    const node = setup(el);
    node.style.height = `${node.scrollHeight}px`;
    void node.offsetHeight; // commit the start height before animating to 0
    node.style.height = '0px';
    node.style.opacity = '0';
    finish(node, done);
}

function finish(node: HTMLElement, done: () => void): void {
    if (reduced()) return done();
    const end = (e?: TransitionEvent) => {
        if (e && e.propertyName !== 'height') return;
        node.removeEventListener('transitionend', end);
        done();
    };
    node.addEventListener('transitionend', end);
    window.setTimeout(end, props.duration + 80); // safety net if no transitionend fires
}

/** Back to natural size once open, so content changes (errors, nested menus) are never clipped. */
function clean(el: Element): void {
    const node = el as HTMLElement;
    node.style.height = '';
    node.style.overflow = '';
    node.style.opacity = '';
    node.style.transition = '';
}
</script>

<template>
    <Transition :css="false" @enter="enter" @after-enter="clean" @leave="leave" @after-leave="clean">
        <component :is="tag" v-show="open" v-bind="$attrs"><slot /></component>
    </Transition>
</template>
