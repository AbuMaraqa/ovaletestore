<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import FlashToast from '@/Components/Common/FlashToast.vue';
import AdminHeader from '@/Components/Navigation/AdminHeader.vue';
import AdminSidebar from '@/Components/Navigation/AdminSidebar.vue';

/** Metronic demo1 shell: fixed sidebar + fixed header (body classes set in app.blade.php). */
const mobileOpen = ref(false);
const off = router.on('navigate', () => (mobileOpen.value = false));

function toggleCollapse(): void {
    document.body.classList.toggle('kt-sidebar-collapse');
}

onMounted(() => document.body.classList.add('demo1', 'kt-sidebar-fixed', 'kt-header-fixed'));
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
            <main id="main" class="grow pt-5" role="main">
                <div class="kt-container-fixed px-4 pb-10 lg:px-8">
                    <slot />
                </div>
            </main>
        </div>
    </div>
    <FlashToast />
</template>
