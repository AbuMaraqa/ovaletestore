<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useFormKitContext } from '@/Composables/useFormKit';
import { useTrans } from '@/Composables/useTrans';

/**
 * Language tabs (ar / en / he) for translated fields. Each tab shows how many of its fields have errors, and a
 * summary link into a hidden language switches to it. `v-model:active` lets several cards share the language.
 * Keyboard: arrows (mirrored in RTL), Home/End. Slot receives the locale and its direction.
 */
const props = withDefaults(defineProps<{ locales?: string[]; errors?: Record<string, string>; prefix?: string; compact?: boolean; optionalFrom?: number }>(), {
    locales: () => ['ar', 'en', 'he'],
    errors: undefined,
    prefix: 'translations',
    compact: false,
    optionalFrom: 1,
});
const active = defineModel<string | undefined>('active');
const { t } = useTrans();
const kit = useFormKitContext();

const labels: Record<string, string> = { ar: 'العربية', en: 'English', he: 'עברית' };
const current = ref(active.value ?? props.locales[0]);
watch(active, (v) => v && (current.value = v));
watch(current, (v) => (active.value = v));

const errorBag = () => props.errors ?? kit?.errors.value ?? {};
const count = (locale: string) => Object.keys(errorBag()).filter((key) => key.startsWith(`${props.prefix}.${locale}.`)).length;
const dirOf = (locale: string) => (locale === 'en' ? 'ltr' : 'rtl');

let off: (() => void) | undefined;
onMounted(() => {
    off = kit?.addRevealer((key) => {
        const locale = props.locales.find((l) => key.startsWith(`${props.prefix}.${l}.`));
        if (locale) current.value = locale;
        return !!locale;
    });
});
onBeforeUnmount(() => off?.());

function onKey(event: KeyboardEvent, index: number): void {
    const rtl = getComputedStyle(event.currentTarget as Element).direction === 'rtl';
    const step = { ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1 }[event.key];
    let next = step === undefined ? -1 : (index + step + props.locales.length) % props.locales.length;
    if (event.key === 'Home') next = 0;
    if (event.key === 'End') next = props.locales.length - 1;
    if (next < 0) return;
    event.preventDefault();
    current.value = props.locales[next];
    ((event.currentTarget as HTMLElement).parentElement?.children[next] as HTMLElement | undefined)?.focus();
}
</script>

<template>
    <div>
        <div :class="['locale-tabs', compact && 'locale-tabs-compact']" role="tablist">
            <button
                v-for="(locale, i) in locales"
                :id="`tab-${prefix}-${locale}`"
                :key="locale"
                type="button"
                role="tab"
                :aria-selected="current === locale"
                :aria-controls="`panel-${prefix}-${locale}`"
                :tabindex="current === locale ? 0 : -1"
                :class="['locale-tab', current === locale && 'is-active']"
                @click="current = locale"
                @keydown="onKey($event, i)"
            >
                {{ labels[locale] ?? locale }}
                <span v-if="i >= optionalFrom && !count(locale)" class="text-[11px] font-normal text-muted-foreground">{{ t('forms.optional') }}</span>
                <span v-if="count(locale)" class="kt-badge kt-badge-xs kt-badge-destructive" :aria-label="t('forms.summary_title', { count: count(locale) })">{{ count(locale) }}</span>
            </button>
        </div>
        <div
            v-for="locale in locales"
            v-show="current === locale"
            :id="`panel-${prefix}-${locale}`"
            :key="locale"
            role="tabpanel"
            :aria-labelledby="`tab-${prefix}-${locale}`"
            :lang="locale"
            :dir="dirOf(locale)"
        >
            <slot :locale="locale" :dir="dirOf(locale)" />
        </div>
    </div>
</template>
