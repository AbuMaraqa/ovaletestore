<script setup lang="ts">
import { computed } from 'vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';

/** Progress of an order along the normal path (Metronic stepper look); a cancelled order is shown as such. */
const props = defineProps<{ status: string; flow: string[] }>();
const { t } = useTrans();

const icons: Record<string, string> = { pending: 'time', confirmed: 'check-circle', processing: 'package', shipped: 'delivery', completed: 'verify' };
const reached = computed(() => props.flow.indexOf(props.status));
/** The last step reached counts as done (not "in progress") once the order is completed. */
const done = (i: number) => i < reached.value || (i === reached.value && i === props.flow.length - 1);
</script>

<template>
    <div v-if="status === 'cancelled'" class="flex items-center gap-3 rounded-xl border border-destructive/30 bg-destructive/5 px-4 py-3 text-destructive">
        <KtIcon name="cross-circle" class="text-2xl" />
        <span class="font-semibold">{{ t('orders.status_cancelled') }}</span>
    </div>
    <ol v-else class="flex items-start" :aria-label="t('common.status')">
        <li v-for="(step, i) in flow" :key="step" class="relative flex flex-1 flex-col items-center gap-2 text-center" :aria-current="i === reached ? 'step' : undefined">
            <!-- connector to the previous step -->
            <span v-if="i > 0" :class="['absolute top-5 h-0.5 w-full -translate-y-1/2 end-1/2', i <= reached ? 'bg-primary' : 'bg-border']" aria-hidden="true" />
            <span
                :class="[
                    'relative z-[1] flex size-10 items-center justify-center rounded-full border-2 transition',
                    done(i) ? 'border-primary bg-primary text-primary-foreground' : '',
                    i === reached && !done(i) ? 'border-primary bg-primary/10 text-primary ring-4 ring-primary/10' : '',
                    i > reached ? 'border-border bg-background text-muted-foreground' : '',
                ]"
            >
                <KtIcon :name="done(i) ? 'check' : icons[step]" class="text-lg" />
            </span>
            <span :class="['text-xs leading-tight sm:text-sm', i <= reached ? 'font-semibold text-mono' : 'text-muted-foreground']">
                {{ t(`orders.status_${step}`) }}
                <span class="sr-only">— {{ done(i) ? t('orders.step_done') : i === reached ? t('orders.step_current') : t('orders.step_upcoming') }}</span>
            </span>
        </li>
    </ol>
</template>
