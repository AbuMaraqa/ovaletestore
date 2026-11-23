<script setup lang="ts">
import type { Avatar } from '@/types';

/** Photo if uploaded, otherwise initials as real text so they render in the site font (Tajawal). */
withDefaults(defineProps<{ avatar: Avatar; name: string; size?: string; ring?: boolean }>(), { size: 'size-9', ring: true });
</script>

<template>
    <img
        v-if="avatar.url"
        :src="avatar.url"
        :alt="name"
        :class="[size, 'shrink-0 rounded-full object-cover', ring && 'border-2 border-green-500']"
    />
    <span
        v-else
        role="img"
        :aria-label="name"
        :class="[size, 'inline-flex shrink-0 select-none items-center justify-center rounded-full font-sans text-sm font-bold leading-none text-white', ring && 'border-2 border-green-500']"
        :style="{ backgroundColor: avatar.color }"
    >
        <span aria-hidden="true" class="translate-y-px">{{ avatar.initials }}</span>
    </span>
</template>
