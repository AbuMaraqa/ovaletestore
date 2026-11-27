<script setup lang="ts">
import { ref } from 'vue';
import Icon from '@/Components/Storefront/Icon.vue';
import { useLocale } from '@/Composables/useLocale';
import { useTrans } from '@/Composables/useTrans';

/**
 * Native horizontal scroller (scroll-snap, touch/trackpad friendly, zero JS dependency) with
 * RTL-aware previous/next buttons. Items are passed in the default slot.
 */
withDefaults(defineProps<{ label: string; tone?: 'light' | 'dark' }>(), { tone: 'light' });
const track = ref<HTMLElement | null>(null);
const { isRtl } = useLocale();
const { t } = useTrans();

function scroll(direction: 1 | -1): void {
    if (!track.value) return;
    const amount = track.value.clientWidth * 0.85 * direction;
    track.value.scrollBy({ left: isRtl.value ? -amount : amount, behavior: 'smooth' });
}
</script>

<template>
    <div class="relative">
        <div
            ref="track"
            class="-mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth px-4 pb-4 [scrollbar-width:none] sm:gap-5 [&::-webkit-scrollbar]:hidden"
            role="region"
            :aria-label="label"
            tabindex="0"
        >
            <slot />
        </div>
        <div class="mt-2 hidden justify-end gap-2 md:flex">
            <button
                v-for="dir in [-1, 1] as const"
                :key="dir"
                type="button"
                :aria-label="dir === -1 ? t('storefront.scroll_prev') : t('storefront.scroll_next')"
                :class="[
                    'inline-flex size-11 items-center justify-center rounded-full ring-1 transition',
                    tone === 'dark' ? 'text-white ring-white/25 hover:bg-white/10' : 'bg-white text-cocoa ring-hairline hover:bg-sand',
                ]"
                @click="scroll(dir)"
            >
                <Icon name="chevron" :class="['size-5', dir === -1 ? 'rotate-180 rtl:rotate-0' : 'rtl:rotate-180']" />
            </button>
        </div>
    </div>
</template>
