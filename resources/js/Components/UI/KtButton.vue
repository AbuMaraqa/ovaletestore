<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import KtIcon from '@/Components/UI/KtIcon.vue';

const props = withDefaults(
    defineProps<{
        variant?: 'primary' | 'secondary' | 'outline' | 'ghost' | 'destructive' | 'mono';
        size?: 'sm' | 'md' | 'lg';
        href?: string;
        type?: 'button' | 'submit';
        icon?: string;
        loading?: boolean;
        disabled?: boolean;
        iconOnly?: boolean;
    }>(),
    { variant: 'primary', size: 'md', href: undefined, type: 'button', icon: undefined, loading: false, disabled: false, iconOnly: false },
);

const classes = computed(() => [
    'kt-btn',
    props.variant !== 'primary' && `kt-btn-${props.variant}`,
    props.size !== 'md' && `kt-btn-${props.size}`,
    props.iconOnly && 'kt-btn-icon',
]);
</script>

<template>
    <Link v-if="href" :href="href" :class="classes">
        <KtIcon v-if="icon" :name="icon" />
        <slot />
    </Link>
    <button v-else :type="type" :class="classes" :disabled="disabled || loading" :aria-busy="loading">
        <span v-if="loading" class="size-4 animate-spin rounded-full border-2 border-current border-e-transparent" aria-hidden="true" />
        <KtIcon v-else-if="icon" :name="icon" />
        <slot />
    </button>
</template>
