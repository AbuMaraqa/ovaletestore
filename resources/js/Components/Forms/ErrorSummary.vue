<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useFormKitContext } from '@/Composables/useFormKit';
import { useTrans } from '@/Composables/useTrans';

/**
 * "N fields need attention" box at the top of a form after a failed save. Each line links to its field:
 * the click opens the right tab / section and focuses the control (useFormKit.reveal). Messages come from
 * Laravel with readable field names (validation.attributes + the FormRequest's attributes()).
 */
const props = withDefaults(defineProps<{ max?: number }>(), { max: 8 });
const kit = useFormKitContext();
const { t } = useTrans();
const box = ref<HTMLElement | null>(null);

const items = computed(() =>
    Object.entries(kit?.errors.value ?? {}).map(([key, message]) => {
        const field = kit?.fields.get(key);
        // The context (language / variation) only when the message does not already name it.
        return { key, message, context: field?.context && !message.includes(field.context) ? field.context : undefined };
    }),
);
const shown = computed(() => items.value.slice(0, props.max));

// A new failed save → move focus here once (screen readers read the list), unless a field already took focus.
watch(
    () => items.value.length,
    async (now, before) => {
        if (now > 0 && before === 0) {
            await nextTick();
            if (!document.activeElement || document.activeElement === document.body) box.value?.focus({ preventScroll: true });
        }
    },
);
</script>

<template>
    <Transition enter-active-class="animate-pop-in" leave-active-class="transition duration-150" leave-to-class="opacity-0">
        <div v-if="items.length" ref="box" class="kt-alert kt-alert-destructive kt-alert-light items-start" role="alert" tabindex="-1">
            <KtIcon name="information-2" class="mt-0.5 text-lg" />
            <div class="min-w-0 grow">
                <p class="font-semibold">{{ t('forms.summary_title', { count: items.length }) }}</p>
                <ul class="mt-1.5 space-y-1 text-sm">
                    <li v-for="item in shown" :key="item.key">
                        <button type="button" class="text-start underline-offset-2 hover:underline focus-visible:underline" @click="kit?.reveal(item.key)">
                            <span v-if="item.context" class="font-medium">{{ item.context }} › </span>{{ item.message }}
                        </button>
                    </li>
                </ul>
                <p v-if="items.length > shown.length" class="mt-1 text-xs opacity-80">{{ t('forms.summary_more', { count: items.length - shown.length }) }}</p>
            </div>
        </div>
    </Transition>
</template>
