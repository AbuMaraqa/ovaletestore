<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import ActivityPanel from '@/Components/Activity/ActivityPanel.vue';
import FlashToast from '@/Components/Common/FlashToast.vue';
import PageLoader from '@/Components/Common/PageLoader.vue';
import AdminFooter from '@/Components/Navigation/AdminFooter.vue';
import AdminHeader from '@/Components/Navigation/AdminHeader.vue';
import AdminSidebar from '@/Components/Navigation/AdminSidebar.vue';
import ConfirmHost from '@/Components/UI/ConfirmHost.vue';
import { usePressFeedback } from '@/Composables/usePressFeedback';

/** Metronic demo1 shell: fixed sidebar + fixed header + content + footer (body classes from app.blade.php). */
const mobileOpen = ref(false);
usePressFeedback();

// Page enter/loading motion lives in <PageLoader> (only real page changes animate, not filters or polling).
const off = router.on('navigate', () => (mobileOpen.value = false));

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
                <div id="admin-page" class="kt-container-fixed pb-7.5">
                    <slot />
                </div>
            </main>
            <AdminFooter />
        </div>
    </div>
    <PageLoader content="#admin-page" tone="admin" />
    <FlashToast />
    <ConfirmHost />
    <ActivityPanel />
</template>
