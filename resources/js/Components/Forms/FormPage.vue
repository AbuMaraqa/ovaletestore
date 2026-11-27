<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import ErrorSummary from '@/Components/Forms/ErrorSummary.vue';
import FormToolbar from '@/Components/Forms/FormToolbar.vue';
import { useFormKit } from '@/Composables/useFormKit';

/**
 * A whole admin form page: sticky toolbar (back, title, unsaved pill, Discard, Save / Ctrl+S), the error summary,
 * then the page's cards (default slot). Wires the form system for everything inside (useFormKit): errors under
 * each field, summary links that jump to them, focus on the first invalid field, unsaved-changes guard.
 *
 * `action(options)` performs the visit (post/put/…) with the options given, so each page keeps its own URL logic.
 */
type VisitHooks = { preserveScroll: boolean; onError: () => void; onSuccess: () => void };
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const props = withDefaults(defineProps<{ form: InertiaForm<any>; action: (options: VisitHooks) => void; title: string; backHref?: string; id: string; guard?: boolean; readonly?: boolean }>(), {
    backHref: undefined,
    guard: true,
    readonly: false,
});
const emit = defineEmits<{ saved: [] }>();

const { focusFirstError } = useFormKit(props.form, { submit: () => run(), guard: props.guard });

function run(): void {
    if (props.form.processing || props.readonly) return;
    props.action({
        preserveScroll: true,
        onError: () => void focusFirstError(),
        onSuccess: () => {
            props.form.defaults();
            emit('saved');
        },
    });
}
</script>

<template>
    <form :id="id" novalidate autocomplete="off" @submit.prevent="run">
        <FormToolbar :title="title" :back-href="backHref" :form-id="id" :dirty="form.isDirty" :processing="form.processing" :readonly="readonly" @discard="form.reset()">
            <template v-if="$slots.status" #status><slot name="status" /></template>
            <template v-if="$slots.actions" #actions><slot name="actions" /></template>
        </FormToolbar>
        <p v-if="$slots.description" class="-mt-2 mb-5 text-sm text-secondary-foreground"><slot name="description" /></p>
        <ErrorSummary class="mb-5" />
        <slot />
    </form>
</template>
