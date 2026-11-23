<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, watch } from 'vue';
import Icon from '@/Components/Storefront/Icon.vue';
import { useLocale } from '@/Composables/useLocale';
import { lockScroll } from '@/Composables/useMotion';
import { useTrans } from '@/Composables/useTrans';

const open = defineModel<boolean>('open', { required: true });
defineProps<{ categories: { id: number; name: string; url: string }[] }>();

const { t } = useTrans();
const { current, available } = useLocale();

watch(open, (value) => {
    document.documentElement.classList.toggle('overflow-hidden', value);
    lockScroll(value);
});
const off = router.on('start', () => (open.value = false));
onBeforeUnmount(() => {
    off();
    document.documentElement.classList.remove('overflow-hidden');
});
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-150" leave-to-class="opacity-0">
            <div v-if="open" class="fixed inset-0 z-50 bg-stone-900/40 backdrop-blur-sm lg:hidden" @click="open = false" />
        </Transition>
        <!-- Drawer slides in from the inline-start edge (right in RTL, left in LTR). -->
        <Transition
            enter-active-class="transition-transform duration-300 ease-out"
            enter-from-class="-translate-x-full rtl:translate-x-full"
            leave-active-class="transition-transform duration-200 ease-in"
            leave-to-class="-translate-x-full rtl:translate-x-full"
        >
            <aside
                v-if="open"
                class="fixed inset-y-0 start-0 z-50 flex w-[85vw] max-w-sm flex-col bg-cream shadow-2xl lg:hidden"
                role="dialog"
                aria-modal="true"
                :aria-label="t('common.menu')"
                @keydown.esc="open = false"
            >
                <div class="flex h-16 items-center justify-between border-b border-stone-200 px-4">
                    <span class="font-semibold">{{ t('common.menu') }}</span>
                    <button type="button" class="inline-flex size-11 items-center justify-center rounded-full hover:bg-stone-100" :aria-label="t('common.close')" @click="open = false">
                        <Icon name="close" class="size-6" />
                    </button>
                </div>
                <nav class="flex-1 overflow-y-auto px-2 py-4" data-lenis-prevent>
                    <Link :href="route('storefront.home')" class="block rounded-xl px-3 py-3 font-medium hover:bg-stone-100">{{ t('common.home') }}</Link>
                    <Link :href="route('storefront.shop')" class="block rounded-xl px-3 py-3 font-medium hover:bg-stone-100">{{ t('storefront.shop') }}</Link>
                    <p class="mt-4 px-3 pb-2 text-sm font-bold text-cocoa">{{ t('storefront.categories') }}</p>
                    <Link v-for="category in categories" :key="category.id" :href="category.url" class="block rounded-xl px-3 py-2.5 text-sm text-stone-700 hover:bg-stone-100">
                        {{ category.name }}
                    </Link>
                </nav>
                <div class="flex gap-2 border-t border-stone-200 p-4">
                    <a
                        v-for="locale in available"
                        :key="locale.code"
                        :href="locale.url"
                        :lang="locale.code"
                        class="chip flex-1 justify-center"
                        :class="{ 'bg-cocoa text-white ring-cocoa': locale.code === current }"
                    >
                        {{ locale.native }}
                    </a>
                </div>
            </aside>
        </Transition>
    </Teleport>
</template>
