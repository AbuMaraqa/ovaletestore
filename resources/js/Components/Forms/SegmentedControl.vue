<script setup lang="ts" generic="T extends string">
/**
 * Pick one of a few options (status, product type): a radio group drawn as segments.
 * Arrow keys move between options (mirrored in RTL, as the eye reads), Home/End jump to the ends.
 */
const model = defineModel<T>({ required: true });
const props = defineProps<{ id: string; label: string; options: { value: T; label: string; dot?: string }[] }>();

function onKey(event: KeyboardEvent, index: number): void {
    const rtl = getComputedStyle(event.currentTarget as Element).direction === 'rtl';
    const step = { ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1, ArrowDown: 1, ArrowUp: -1 }[event.key];
    let next = step === undefined ? -1 : (index + step + props.options.length) % props.options.length;
    if (event.key === 'Home') next = 0;
    if (event.key === 'End') next = props.options.length - 1;
    if (next < 0) return;
    event.preventDefault();
    model.value = props.options[next].value;
    ((event.currentTarget as HTMLElement).parentElement?.children[next] as HTMLElement | undefined)?.focus();
}
</script>

<template>
    <div :id="id" role="radiogroup" :aria-label="label" class="segmented">
        <button
            v-for="(option, i) in options"
            :key="option.value"
            type="button"
            role="radio"
            :aria-checked="model === option.value"
            :tabindex="model === option.value ? 0 : -1"
            :class="['segmented-item', model === option.value && 'is-active']"
            @click="model = option.value"
            @keydown="onKey($event, i)"
        >
            <span v-if="option.dot" :class="['size-1.5 rounded-full', option.dot]" aria-hidden="true" />
            {{ option.label }}
        </button>
    </div>
</template>
