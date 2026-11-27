<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { onClickOutside } from '@/Composables/onClickOutside';
import { useActivityPanel } from '@/Composables/useActivityPanel';
import { useConfirm } from '@/Composables/useConfirm';
import { useTrans } from '@/Composables/useTrans';

/**
 * Row actions, Metronic/Filament style: the main action (edit, else view) inline; history and delete in a
 * labelled "⋯" menu so a destructive click is never one stray tap away. Each action exists only when the
 * server sent its URL — i.e. when the user holds that permission. Labels include the row name for screen readers.
 */
const props = defineProps<{
    viewUrl?: string | null;
    editUrl?: string | null;
    deleteUrl?: string | null;
    label?: string;
    activity?: { type: string; id: number } | null;
}>();

const { t } = useTrans();
const { confirm } = useConfirm();
const panel = useActivityPanel();
const named = (key: string) => (props.label ? `${t(key)}: ${props.label}` : t(key));

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLButtonElement | null>(null);
const menu = ref<HTMLElement | null>(null);
onClickOutside(root, () => (open.value = false));

async function toggle(): Promise<void> {
    open.value = !open.value;
    if (open.value) {
        await nextTick();
        menu.value?.querySelector<HTMLElement>('button, a')?.focus();
    }
}
function closeMenu(): void {
    open.value = false;
    trigger.value?.focus();
}
function onMenuKey(event: KeyboardEvent): void {
    const items = [...(menu.value?.querySelectorAll<HTMLElement>('button, a') ?? [])];
    const index = items.indexOf(document.activeElement as HTMLElement);
    if (event.key === 'ArrowDown') items[(index + 1) % items.length]?.focus();
    else if (event.key === 'ArrowUp') items[(index - 1 + items.length) % items.length]?.focus();
    else if (event.key === 'Escape') closeMenu();
    else return;
    event.preventDefault();
}

function showHistory(): void {
    open.value = false;
    if (props.activity) panel.open({ ...props.activity, label: props.label ?? '' });
}

async function destroy(): Promise<void> {
    open.value = false;
    const ok = await confirm({
        title: props.label ? t('common.delete_named', { name: props.label }) : t('common.delete'),
        message: t('common.confirm_delete'),
        confirmLabel: t('common.delete'),
    });
    if (ok && props.deleteUrl) router.delete(props.deleteUrl, { preserveScroll: true });
}

const hasMenu = () => Boolean(props.deleteUrl || (props.activity && panel.allowed()) || (props.editUrl && props.viewUrl));
</script>

<template>
    <div ref="root" class="relative flex items-center justify-end gap-1">
        <slot />
        <Link
            v-if="editUrl || viewUrl"
            :href="(editUrl ?? viewUrl)!"
            class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost text-secondary-foreground hover:text-primary pointer-coarse:size-10"
            :title="t(editUrl ? 'common.edit' : 'common.view')"
            :aria-label="named(editUrl ? 'common.edit' : 'common.view')"
        >
            <KtIcon :name="editUrl ? 'notepad-edit' : 'eye'" class="text-base" />
        </Link>

        <button
            v-if="hasMenu()"
            ref="trigger"
            type="button"
            class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost text-secondary-foreground hover:text-primary pointer-coarse:size-10"
            :aria-label="named('table.more_actions')"
            aria-haspopup="menu"
            :aria-expanded="open"
            @click="toggle"
        >
            <KtIcon name="dots-vertical" class="text-base" />
        </button>

        <Transition leave-active-class="transition duration-100 ease-in" leave-to-class="opacity-0 -translate-y-1">
            <div v-if="open" ref="menu" role="menu" class="animate-pop-in absolute end-0 top-full z-30 mt-1 w-48 rounded-xl border border-border bg-popover p-1.5 shadow-lg" @keydown="onMenuKey">
                <Link v-if="editUrl && viewUrl" :href="viewUrl" role="menuitem" class="kt-dropdown-menu-link w-full" @click="open = false">
                    <KtIcon name="eye" /> {{ t('common.view') }}
                </Link>
                <button v-if="activity && panel.allowed()" type="button" role="menuitem" class="kt-dropdown-menu-link w-full" @click="showHistory">
                    <KtIcon name="time" /> {{ t('activity.title') }}
                </button>
                <template v-if="deleteUrl">
                    <div class="my-1 border-t border-border" role="separator" />
                    <button type="button" role="menuitem" class="kt-dropdown-menu-link w-full text-destructive hover:bg-destructive/10" @click="destroy">
                        <KtIcon name="trash" /> {{ t('common.delete') }}
                    </button>
                </template>
            </div>
        </Transition>
    </div>
</template>
