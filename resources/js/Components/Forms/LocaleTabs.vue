<script setup lang="ts">
import { computed, ref } from 'vue';

/**
 * Translation tabs (ar / en / he). Tabs with validation errors get a red dot so editors find them.
 * Slot receives the active locale and its text direction.
 */
const props = withDefaults(defineProps<{ locales?: string[]; errors?: Record<string, string>; prefix?: string }>(), {
    locales: () => ['ar', 'en', 'he'],
    errors: () => ({}),
    prefix: 'translations',
});

const labels: Record<string, string> = { ar: 'العربية', en: 'English', he: 'עברית' };
const active = ref(props.locales[0]);
const dir = computed(() => (active.value === 'en' ? 'ltr' : 'rtl'));
const hasError = (locale: string) => Object.keys(props.errors).some((key) => key.startsWith(`${props.prefix}.${locale}.`));
</script>

<template>
    <div>
        <div class="kt-tabs kt-tabs-line mb-5 flex gap-1 border-b border-border" role="tablist">
            <button
                v-for="locale in locales"
                :key="locale"
                type="button"
                role="tab"
                :aria-selected="active === locale"
                :class="['kt-tab-toggle relative px-4 py-2.5 text-sm font-medium', active === locale ? 'active border-b-2 border-primary text-primary' : 'text-muted-foreground hover:text-foreground']"
                @click="active = locale"
            >
                {{ labels[locale] ?? locale }}
                <span v-if="hasError(locale)" class="absolute end-1 top-2 size-1.5 rounded-full bg-destructive" />
            </button>
        </div>
        <div v-for="locale in locales" v-show="active === locale" :key="locale" role="tabpanel" :lang="locale" :dir="locale === 'en' ? 'ltr' : 'rtl'">
            <slot :locale="locale" :dir="dir" />
        </div>
    </div>
</template>
