<script setup lang="ts">
import { computed, ref } from 'vue';
import SocialIcon from '@/Components/Storefront/SocialIcon.vue';
import { useTrans } from '@/Composables/useTrans';
import type { StoreLocation } from '@/types';

/**
 * Store locations with one Google Maps embed: picking a location moves the map.
 * `tone` adapts the list to the dark footer or a light page.
 */
const props = withDefaults(defineProps<{ locations: StoreLocation[]; tone?: 'dark' | 'light' }>(), { tone: 'light' });
const { t } = useTrans();
const active = ref(0);
const current = computed(() => props.locations[active.value] ?? props.locations[0]);
const dark = computed(() => props.tone === 'dark');
</script>

<template>
    <div v-if="locations.length" class="grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
        <ul class="grid content-start gap-3" :aria-label="t('storefront.our_branches')">
            <li v-for="(location, index) in locations" :key="index">
                <div
                    :class="[
                        'flex gap-3 rounded-2xl p-4 ring-1 transition',
                        dark
                            ? index === active ? 'bg-white/10 ring-white/25' : 'ring-white/10 hover:bg-white/5'
                            : index === active ? 'bg-brand-50 ring-brand-300' : 'bg-white ring-stone-200 hover:ring-brand-200',
                    ]"
                >
                    <SocialIcon name="pin" :class="['mt-0.5 size-5 shrink-0', dark ? 'text-[#e4cfa3]' : 'text-brand-600']" />
                    <div class="min-w-0 flex-1">
                        <button
                            type="button"
                            :aria-pressed="index === active"
                            :class="['text-start text-base font-bold', dark ? 'text-white' : 'text-cocoa']"
                            @click="active = index"
                        >
                            {{ location.name }}
                        </button>
                        <p :class="['mt-1 text-sm leading-relaxed', dark ? 'text-stone-300' : 'text-stone-600']">{{ location.address }}</p>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm font-semibold">
                            <button
                                v-if="locations.length > 1"
                                type="button"
                                :class="dark ? 'text-[#e4cfa3] hover:text-white' : 'text-brand-700 hover:text-brand-800'"
                                @click="active = index"
                            >
                                {{ t('storefront.show_on_map') }}
                            </button>
                            <a :href="location.directions" target="_blank" rel="noopener" :class="dark ? 'text-[#e4cfa3] hover:text-white' : 'text-brand-700 hover:text-brand-800'">
                                {{ t('storefront.directions') }}
                            </a>
                        </div>
                    </div>
                </div>
            </li>
        </ul>

        <div :class="['overflow-hidden rounded-3xl ring-1', dark ? 'ring-white/10' : 'ring-stone-200']">
            <iframe
                :key="current.map_embed"
                :src="current.map_embed"
                :title="t('storefront.map_of', { name: current.name ?? '' })"
                class="block h-72 w-full border-0 sm:h-80"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                allowfullscreen
            />
        </div>
    </div>
</template>
