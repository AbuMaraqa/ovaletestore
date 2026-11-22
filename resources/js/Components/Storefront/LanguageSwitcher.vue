<script setup lang="ts">
import { onClickOutside } from '@/Composables/onClickOutside';
import { ref } from 'vue';
import Icon from '@/Components/Storefront/Icon.vue';
import { useLocale } from '@/Composables/useLocale';
import { useTrans } from '@/Composables/useTrans';

const { current, available } = useLocale();
const { t } = useTrans();
const open = ref(false);
const root = ref<HTMLElement | null>(null);
onClickOutside(root, () => (open.value = false));
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="inline-flex h-11 items-center gap-1.5 rounded-full px-3 text-sm font-medium hover:bg-stone-100"
            :aria-label="t('common.language')"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click="open = !open"
        >
            <Icon name="globe" class="size-5" />
            <span class="uppercase">{{ current }}</span>
        </button>
        <Transition enter-active-class="transition duration-150" enter-from-class="opacity-0 scale-95" leave-active-class="transition duration-100" leave-to-class="opacity-0">
            <ul v-if="open" role="listbox" class="absolute end-0 top-full z-50 mt-2 w-44 origin-top-right overflow-hidden rounded-2xl bg-white p-1.5 shadow-lift ring-1 ring-stone-100">
                <li v-for="locale in available" :key="locale.code" role="option" :aria-selected="locale.code === current">
                    <!-- Plain anchor: switching language is a full reload (lang/dir/fonts change). -->
                    <a
                        :href="locale.url"
                        :hreflang="locale.code"
                        :lang="locale.code"
                        class="flex items-center justify-between rounded-xl px-3 py-2.5 text-sm hover:bg-stone-50"
                        :class="locale.code === current ? 'font-semibold text-brand-700' : 'text-stone-700'"
                    >
                        {{ locale.native }}
                        <Icon v-if="locale.code === current" name="check" class="size-4" />
                    </a>
                </li>
            </ul>
        </Transition>
    </div>
</template>
