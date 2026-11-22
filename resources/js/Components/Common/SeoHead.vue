<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Seo, SharedProps } from '@/types';

/** Mirrors the server-rendered head (app.blade.php) on client-side navigation. head-key values match the Blade `inertia` keys. */
const page = usePage<SharedProps>();
const seo = computed<Seo | undefined>(() => page.props.seo);
</script>

<template>
    <Head v-if="seo">
        <title>{{ seo.title }}</title>
        <meta v-if="seo.description" head-key="description" name="description" :content="seo.description" />
        <meta head-key="robots" name="robots" :content="seo.robots" />
        <link head-key="canonical" rel="canonical" :href="seo.canonical" />
        <link
            v-for="alt in seo.alternates"
            :key="alt.hreflang"
            :head-key="`alternate-${alt.hreflang}`"
            rel="alternate"
            :hreflang="alt.hreflang"
            :href="alt.href"
        />
        <meta head-key="og:type" property="og:type" :content="seo.og.type" />
        <meta head-key="og:title" property="og:title" :content="seo.title" />
        <meta v-if="seo.description" head-key="og:description" property="og:description" :content="seo.description" />
        <meta head-key="og:url" property="og:url" :content="seo.canonical" />
        <meta head-key="og:site_name" property="og:site_name" :content="seo.og.site_name" />
        <meta head-key="og:locale" property="og:locale" :content="seo.og.locale" />
        <meta v-if="seo.og.image" head-key="og:image" property="og:image" :content="seo.og.image" />
        <meta v-if="seo.og.image" head-key="twitter:image" name="twitter:image" :content="seo.og.image" />
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
    </Head>
</template>
