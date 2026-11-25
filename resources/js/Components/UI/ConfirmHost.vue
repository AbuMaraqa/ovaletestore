<script setup lang="ts">
import { computed } from 'vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import KtModal from '@/Components/UI/KtModal.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useTrans } from '@/Composables/useTrans';

/** App-wide confirmation (alertdialog). Destructive prompts open with focus on Cancel — the safe default. */
const { state, settle } = useConfirm();
const { t } = useTrans();
const open = computed({ get: () => state.open, set: (value: boolean) => !value && settle(false) });
</script>

<template>
    <KtModal v-model:open="open" :title="state.title" width="max-w-md" role="alertdialog" :initial-focus="state.danger ? '[data-confirm-cancel]' : '[data-confirm-ok]'">
        <div class="flex items-start gap-4">
            <span :class="['flex size-11 shrink-0 items-center justify-center rounded-full', state.danger ? 'bg-destructive/10 text-destructive' : 'bg-primary/10 text-primary']">
                <KtIcon :name="state.danger ? 'information-2' : 'question-2'" class="text-2xl" />
            </span>
            <p class="whitespace-pre-line pt-2 text-sm text-secondary-foreground">{{ state.message || t('common.confirm_delete') }}</p>
        </div>
        <template #footer>
            <div class="flex w-full justify-end gap-2.5">
                <button type="button" class="kt-btn kt-btn-outline" data-confirm-cancel @click="settle(false)">{{ t('common.cancel') }}</button>
                <button type="button" :class="['kt-btn', state.danger ? 'kt-btn-destructive' : 'kt-btn-primary']" data-confirm-ok @click="settle(true)">{{ state.confirmLabel }}</button>
            </div>
        </template>
    </KtModal>
</template>
