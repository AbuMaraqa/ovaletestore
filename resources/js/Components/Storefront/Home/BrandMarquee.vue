<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Icon from '@/Components/Storefront/Icon.vue';
import { useGsap } from '@/Composables/useMotion';
import { useLocale } from '@/Composables/useLocale';
import { useTrans } from '@/Composables/useTrans';

/**
 * Two logo rows drifting in opposite directions (GSAP). Each row is rendered twice and moves exactly one
 * copy's width, so the loop is seamless; hover/focus pauses that row. The rows have vertical padding
 * inside the clipping box, so card frames and hover shadows are never cut.
 * With reduced motion the rows are plain, scrollable strips.
 */
type Brand = { id: number; name: string; url: string; logo: string | null };
const props = defineProps<{ brands: Brand[] }>();
const { t } = useTrans();
const { isRtl } = useLocale();
const root = ref<HTMLElement | null>(null);

/** Split in two; with only a few brands both rows show all of them (the second one reversed). */
const rows = computed<Brand[][]>(() => {
    const list = props.brands;
    if (list.length < 10) return [list, [...list].reverse()];
    const half = Math.ceil(list.length / 2);
    return [list.slice(0, half), list.slice(half)];
});

useGsap(root, ({ gsap }) => {
    root.value!.querySelectorAll<HTMLElement>('[data-marquee-row]').forEach((row, index) => {
        const track = row.querySelector('[data-marquee-track]')!;
        const away = isRtl.value ? 50 : -50;
        const reverse = index % 2 === 1;
        const tween = gsap.fromTo(track, { xPercent: reverse ? away : 0 }, {
            xPercent: reverse ? 0 : away,
            duration: Math.max(18, (rows.value[index]?.length ?? 6) * 3.6),
            ease: 'none',
            repeat: -1,
        });
        row.addEventListener('mouseenter', () => tween.pause());
        row.addEventListener('mouseleave', () => tween.resume());
        row.addEventListener('focusin', () => tween.pause());
        row.addEventListener('focusout', () => tween.resume());
    });
});
</script>

<template>
    <section v-if="brands.length" ref="root" class="mt-24">
        <div class="container-store text-center">
            <h2 data-reveal class="text-3xl font-extrabold tracking-tight text-cocoa sm:text-4xl">{{ t('storefront.brands_title') }}</h2>
            <p data-reveal class="mx-auto mt-3 max-w-xl text-stone-500">{{ t('storefront.brands_subtitle') }}</p>
        </div>

        <div class="mt-8 space-y-1 [mask-image:linear-gradient(90deg,transparent,#000_10%,#000_90%,transparent)]">
            <div
                v-for="(row, r) in rows"
                :key="r"
                data-marquee-row
                class="overflow-hidden py-3 motion-reduce:overflow-x-auto"
            >
                <div data-marquee-track class="flex w-max gap-4 px-2 will-change-transform">
                    <Link
                        v-for="(brand, i) in [...row, ...row]"
                        :key="`${r}-${i}`"
                        :href="brand.url"
                        class="group flex h-24 w-40 shrink-0 items-center justify-center rounded-2xl bg-white px-5 py-3 ring-1 ring-stone-200/80 transition duration-300 hover:-translate-y-0.5 hover:shadow-lift hover:ring-brand-300 focus-visible:ring-2 focus-visible:ring-brand-500 sm:h-28 sm:w-48"
                        :aria-hidden="i >= row.length"
                        :tabindex="i >= row.length ? -1 : undefined"
                        :aria-label="brand.name"
                    >
                        <img
                            v-if="brand.logo"
                            :src="brand.logo"
                            :alt="brand.name"
                            loading="lazy"
                            decoding="async"
                            class="max-h-full w-auto object-contain mix-blend-multiply transition duration-300 group-hover:scale-105"
                            width="200"
                            height="200"
                        />
                        <span v-else class="font-bold text-cocoa">{{ brand.name }}</span>
                    </Link>
                </div>
            </div>
        </div>

        <div class="mt-8 text-center">
            <Link :href="route('storefront.brands')" class="btn-ghost group h-11 gap-2 px-6 text-sm font-bold">
                {{ t('storefront.all_brands') }}
                <Icon name="arrow" class="size-4 transition group-hover:translate-x-0.5 rtl:-scale-x-100 rtl:group-hover:-translate-x-0.5" />
            </Link>
        </div>
    </section>
</template>
