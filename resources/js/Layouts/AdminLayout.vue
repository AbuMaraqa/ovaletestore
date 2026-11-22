<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import FlashToast from '@/Components/Common/FlashToast.vue';
import AdminFooter from '@/Components/Navigation/AdminFooter.vue';
import AdminHeader from '@/Components/Navigation/AdminHeader.vue';
import AdminSidebar from '@/Components/Navigation/AdminSidebar.vue';
import { prefersReducedMotion } from '@/Composables/useMotion';

/** Metronic demo1 shell: fixed sidebar + fixed header + content + footer (body classes from app.blade.php). */
const mobileOpen = ref(false);
const content = ref<HTMLElement | null>(null);

// Subtle page-enter motion for the content area on every Inertia visit.
const off = router.on('navigate', () => {
    mobileOpen.value = false;
    if (content.value && !prefersReducedMotion()) {
        gsap.fromTo(content.value, { autoAlpha: 0, y: 12 }, { autoAlpha: 1, y: 0, duration: 0.45, ease: 'power2.out', clearProps: 'all' });
    }
});

function toggleCollapse(): void {
    document.body.classList.toggle('kt-sidebar-collapse');
    try {
        localStorage.setItem('kt-sidebar-collapse', document.body.classList.contains('kt-sidebar-collapse') ? '1' : '0');
    } catch {
        /* storage unavailable */
    }
}

onMounted(() => {
    document.body.classList.add('demo1', 'kt-sidebar-fixed', 'kt-header-fixed');
    try {
        if (localStorage.getItem('kt-sidebar-collapse') === '1') document.body.classList.add('kt-sidebar-collapse');
    } catch {
        /* storage unavailable */
    }
});
onBeforeUnmount(() => {
    off();
    document.body.classList.remove('demo1', 'kt-sidebar-fixed', 'kt-header-fixed', 'kt-sidebar-collapse');
});
</script>

<template>
    <div class="flex grow">
        <AdminSidebar :mobile-open="mobileOpen" @close="mobileOpen = false" @toggle-collapse="toggleCollapse" />
        <div class="kt-wrapper flex min-h-screen grow flex-col">
            <AdminHeader @open-sidebar="mobileOpen = true" />
            <main id="content" class="grow pt-5" role="main">
                <div ref="content" class="kt-container-fixed pb-7.5">
                    <slot />
                </div>
            </main>
            <AdminFooter />
        </div>
    </div>
    <FlashToast />
</template>
