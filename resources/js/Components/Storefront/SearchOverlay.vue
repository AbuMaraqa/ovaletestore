<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import Icon from '@/Components/Storefront/Icon.vue';
import { useDebounce } from '@/Composables/useDebounce';
import { lockScroll, prefersReducedMotion } from '@/Composables/useMotion';
import { useTrans } from '@/Composables/useTrans';

/**
 * Full-screen search (same idea as the original store: dark overlay, big underlined field, round gold button)
 * with live suggestions — products with thumbnail + price, and matching categories.
 */
interface Suggestions {
    products: { id: number; name: string; url: string; image: string | null; price: string }[];
    categories: { id: number; name: string; url: string }[];
    all_url: string;
}

const open = defineModel<boolean>('open', { required: true });
const { t } = useTrans();
const input = ref<HTMLInputElement | null>(null);
const panel = ref<HTMLElement | null>(null);
const q = ref('');
const loading = ref(false);
const results = ref<Suggestions | null>(null);
let controller: AbortController | null = null;

const fetchSuggestions = useDebounce(async (term: string) => {
    controller?.abort();
    if (term.length < 2) {
        results.value = null;
        loading.value = false;
        return;
    }
    controller = new AbortController();
    try {
        const response = await fetch(`${route('storefront.search.suggest')}?q=${encodeURIComponent(term)}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });
        if (response.ok) results.value = await response.json();
    } catch {
        /* aborted or offline — keep previous results */
    } finally {
        loading.value = false;
    }
}, 250);

watch(q, (term) => {
    loading.value = term.trim().length >= 2;
    fetchSuggestions(term.trim());
});

watch(open, async (value) => {
    lockScroll(value);
    document.documentElement.classList.toggle('overflow-hidden', value);
    if (!value) return;
    await nextTick();
    input.value?.focus();
    if (panel.value && !prefersReducedMotion()) {
        gsap.fromTo(panel.value, { autoAlpha: 0, y: 24 }, { autoAlpha: 1, y: 0, duration: 0.45, ease: 'power3.out' });
    }
});

const off = router.on('start', () => (open.value = false));
onBeforeUnmount(() => {
    off();
    controller?.abort();
    document.documentElement.classList.remove('overflow-hidden');
});

function submit(): void {
    const term = q.value.trim();
    if (term) router.get(route('storefront.shop'), { q: term });
}
</script>

<template>
    <Teleport to="body">
        <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-150" leave-to-class="opacity-0">
            <div v-if="open" class="fixed inset-0 z-[120] overflow-y-auto bg-cocoa/97 text-white backdrop-blur-sm" role="dialog" aria-modal="true" :aria-label="t('common.search')" data-lenis-prevent @keydown.esc="open = false">
                <button type="button" class="fixed end-5 top-5 inline-flex size-12 items-center justify-center rounded-full text-white/80 hover:bg-white/10 hover:text-white" :aria-label="t('common.close')" @click="open = false">
                    <Icon name="close" class="size-7" />
                </button>

                <div ref="panel" class="mx-auto w-full max-w-4xl px-5 pt-[18vh] pb-16">
                    <form role="search" class="flex items-center gap-4 border-b-2 border-white/25 pb-4 focus-within:border-brand-300" @submit.prevent="submit">
                        <label for="overlay-search" class="sr-only">{{ t('common.search') }}</label>
                        <input
                            id="overlay-search"
                            ref="input"
                            v-model="q"
                            type="search"
                            maxlength="100"
                            autocomplete="off"
                            enterkeyhint="search"
                            :placeholder="t('common.search_placeholder')"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-2xl font-medium text-white placeholder:text-white/40 focus:ring-0 sm:text-4xl"
                        />
                        <button type="submit" class="inline-flex size-14 shrink-0 items-center justify-center rounded-full bg-[#c9a24d] text-cocoa transition hover:scale-105 hover:bg-[#d8b25c]" :aria-label="t('common.search')">
                            <span v-if="loading" class="size-5 animate-spin rounded-full border-2 border-cocoa border-e-transparent" aria-hidden="true" />
                            <Icon v-else name="search" class="size-6" />
                        </button>
                    </form>

                    <div v-if="results" class="mt-10" aria-live="polite">
                        <div v-if="results.categories.length" class="mb-8 flex flex-wrap gap-2">
                            <Link v-for="c in results.categories" :key="c.id" :href="c.url" class="rounded-full bg-white/10 px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/15 hover:bg-white/20">
                                {{ c.name }}
                            </Link>
                        </div>

                        <ul v-if="results.products.length" class="grid gap-3 sm:grid-cols-2">
                            <li v-for="p in results.products" :key="p.id">
                                <Link :href="p.url" class="flex items-center gap-4 rounded-2xl p-2.5 transition hover:bg-white/10">
                                    <span class="size-16 shrink-0 overflow-hidden rounded-xl bg-white">
                                        <img v-if="p.image" :src="p.image" alt="" class="size-full object-contain p-1" loading="lazy" />
                                    </span>
                                    <span class="min-w-0">
                                        <span class="line-clamp-2 text-sm font-semibold leading-snug">{{ p.name }}</span>
                                        <span class="mt-1 block text-sm font-bold text-brand-300" dir="ltr">{{ p.price }}</span>
                                    </span>
                                </Link>
                            </li>
                        </ul>
                        <p v-else class="text-center text-white/60">{{ t('common.no_results') }}</p>

                        <div v-if="results.products.length" class="mt-8 text-center">
                            <Link :href="results.all_url" class="inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 text-sm font-bold text-cocoa hover:bg-brand-100">
                                {{ t('storefront.view_all_results') }}
                                <Icon name="arrow" class="size-4 rtl:-scale-x-100" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
