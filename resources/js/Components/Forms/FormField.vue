<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useFormKitContext } from '@/Composables/useFormKit';
import { useTrans } from '@/Composables/useTrans';

/**
 * One labelled field: label (+ required / optional marker, live counter), the control (slot), help, error.
 *
 * - The control with `id` gets aria-invalid / aria-describedby / aria-required set automatically, so any input, select,
 *   textarea or editor placed in the slot is accessible and KTUI shows its invalid state (KTUI hides `.kt-form-message`
 *   unless something has aria-invalid — that is why errors used to be invisible; the error here has its own class).
 * - With `error-key` (e.g. "translations.ar.name") the field reads its error from the page form (useFormKit), registers
 *   itself for <ErrorSummary> jumps, and clears the error as soon as the control is edited.
 * - layout="horizontal": label column + control (settings pages, Metronic account-settings pattern).
 */
const props = withDefaults(
    defineProps<{
        id: string;
        label?: string;
        error?: string;
        errorKey?: string;
        help?: string;
        required?: boolean;
        optional?: boolean;
        counter?: { value: number; max: number };
        context?: string;
        layout?: 'stacked' | 'horizontal';
    }>(),
    { label: undefined, error: undefined, errorKey: undefined, help: undefined, counter: undefined, context: undefined, layout: 'stacked' },
);
const { t } = useTrans();
const kit = useFormKitContext();
const root = ref<HTMLElement | null>(null);

const message = computed(() => props.error ?? (props.errorKey ? kit?.errors.value[props.errorKey] : undefined));
const describedBy = computed(() => (message.value ? `${props.id}-error` : props.help ? `${props.id}-help` : undefined));
const counterTone = computed(() => {
    if (!props.counter) return '';
    const ratio = props.counter.value / props.counter.max;
    return ratio > 1 ? 'text-destructive' : ratio >= 0.9 ? 'text-warning' : 'text-muted-foreground';
});

function control(): HTMLElement | null {
    return root.value?.querySelector<HTMLElement>(`#${CSS.escape(props.id)}`) ?? null;
}
function syncAria(): void {
    const el = control();
    if (!el) return;
    el.setAttribute('aria-invalid', message.value ? 'true' : 'false');
    if (describedBy.value) el.setAttribute('aria-describedby', describedBy.value);
    else el.removeAttribute('aria-describedby');
    if (props.required) el.setAttribute('aria-required', 'true');
}
watch([message, describedBy], syncAria, { flush: 'post' });

let unregister: (() => void) | undefined;
onMounted(() => {
    syncAria();
    if (kit && props.errorKey) unregister = kit.register({ key: props.errorKey, id: props.id, label: props.label ?? '', context: props.context });
});
onBeforeUnmount(() => unregister?.());

function onEdit(): void {
    if (props.errorKey && message.value) kit?.clear(props.errorKey);
}
</script>

<template>
    <div
        ref="root"
        :data-field-key="errorKey"
        :class="['form-field', layout === 'horizontal' ? 'flex flex-col gap-2 lg:flex-row lg:items-baseline lg:gap-6' : 'flex flex-col gap-2']"
        @input="onEdit"
        @change="onEdit"
    >
        <div v-if="label || counter" :class="['flex items-baseline justify-between gap-3', layout === 'horizontal' && 'lg:w-56 lg:shrink-0']">
            <label v-if="label" :for="id" class="text-sm font-medium text-foreground">
                {{ label }}<span v-if="required" class="ms-0.5 text-destructive" aria-hidden="true">*</span>
                <span v-if="required" class="sr-only">({{ t('forms.required') }})</span>
                <span v-if="optional" class="ms-1.5 text-xs font-normal text-muted-foreground">{{ t('forms.optional') }}</span>
            </label>
            <span v-if="counter" :class="['shrink-0 text-xs tabular-nums', counterTone]" dir="ltr">{{ counter.value }} / {{ counter.max }}</span>
        </div>
        <div class="flex min-w-0 grow flex-col gap-1.5">
            <slot :invalid="!!message" :described-by="describedBy" />
            <!-- Not role="alert": after a save <ErrorSummary> announces all errors once. -->
            <p v-if="message" :id="`${id}-error`" class="form-field-error">
                <KtIcon name="information-2" class="mt-px shrink-0 text-sm" />
                <span>{{ message }}</span>
            </p>
            <p v-else-if="help" :id="`${id}-help`" class="text-xs text-secondary-foreground">{{ help }}</p>
        </div>
    </div>
</template>
