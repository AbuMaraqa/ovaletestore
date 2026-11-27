<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useId } from 'vue';
import KtCollapse from '@/Components/UI/KtCollapse.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useFormKitContext } from '@/Composables/useFormKit';
import { useTrans } from '@/Composables/useTrans';

/**
 * A titled card of a form. `errors` lists the error-key prefixes inside it: the header then shows how many need
 * attention, and a collapsible card opens itself when a summary link points inside it.
 */
const props = withDefaults(
    defineProps<{ title: string; description?: string; icon?: string; errors?: string[]; collapsible?: boolean; open?: boolean; flush?: boolean }>(),
    { description: undefined, icon: undefined, errors: () => [], collapsible: false, open: true, flush: false },
);
const kit = useFormKitContext();
const { t } = useTrans();
const expanded = ref(props.open);
const bodyId = useId();
const count = computed(() => (kit && props.errors.length ? kit.count(props.errors) : 0));

let off: (() => void) | undefined;
onMounted(() => {
    off = kit?.addRevealer((key) => {
        const mine = props.errors.some((p) => key === p || key.startsWith(`${p}.`));
        if (mine) expanded.value = true;
        return mine;
    });
});
onBeforeUnmount(() => off?.());
</script>

<template>
    <section class="kt-card">
        <header class="kt-card-header min-h-14 flex-wrap gap-y-2">
            <component
                :is="collapsible ? 'button' : 'div'"
                v-bind="collapsible ? { type: 'button', 'aria-expanded': expanded, 'aria-controls': bodyId } : {}"
                class="flex min-w-0 grow items-center gap-3 text-start"
                @click="collapsible && (expanded = !expanded)"
            >
                <span v-if="icon" class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-lg text-primary"><KtIcon :name="icon" /></span>
                <span class="min-w-0">
                    <span class="kt-card-title flex items-center gap-2">
                        {{ title }}
                        <span v-if="count" class="kt-badge kt-badge-xs kt-badge-destructive" :title="t('forms.summary_title', { count })">{{ count }}</span>
                    </span>
                    <span v-if="description" class="mt-0.5 block text-xs font-normal text-secondary-foreground">{{ description }}</span>
                </span>
                <KtIcon v-if="collapsible" name="down" :class="['ms-auto text-muted-foreground transition-transform duration-200', expanded && 'rotate-180']" />
            </component>
            <div v-if="$slots.toolbar" class="flex items-center gap-2"><slot name="toolbar" /></div>
        </header>
        <KtCollapse v-if="collapsible" :open="expanded">
            <div :id="bodyId" :class="flush ? '' : 'kt-card-content'"><slot /></div>
        </KtCollapse>
        <div v-else :class="flush ? '' : 'kt-card-content'"><slot /></div>
    </section>
</template>
