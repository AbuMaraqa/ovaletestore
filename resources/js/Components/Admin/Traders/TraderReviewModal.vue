<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import type { FilterOption } from '@/Components/DataTable/types';
import KtButton from '@/Components/UI/KtButton.vue';
import KtModal from '@/Components/UI/KtModal.vue';
import { useTrans } from '@/Composables/useTrans';

/** Approve a trader on a price tier, move them to another tier, or reject the request. */
const props = defineProps<{ trader: { id: number; name: string; business_name: string | null; status: string | null; tier_id: number | null } | null; tiers: FilterOption[] }>();
const emit = defineEmits<{ close: [] }>();
const { t } = useTrans();

const open = computed({ get: () => props.trader !== null, set: (v: boolean) => !v && emit('close') });
const form = useForm<{ status: 'approved' | 'rejected'; price_tier_id: number | string | null }>({ status: 'approved', price_tier_id: null });

watch(() => props.trader, (trader) => {
    form.clearErrors();
    form.status = trader?.status === 'rejected' ? 'rejected' : 'approved';
    form.price_tier_id = trader?.tier_id ?? (props.tiers[0]?.value ?? null);
});

const submit = () => props.trader && form.patch(route('admin.traders.review', props.trader.id), { preserveScroll: true, onSuccess: () => emit('close') });
</script>

<template>
    <KtModal v-model:open="open" :title="t('traders.review_title')" width="max-w-md">
        <form v-if="trader" id="trader-review" class="space-y-5" @submit.prevent="submit">
            <div class="rounded-lg bg-muted/50 p-3 text-sm">
                <p class="font-semibold text-mono">{{ trader.name }}</p>
                <p v-if="trader.business_name" class="text-secondary-foreground">{{ trader.business_name }}</p>
            </div>
            <fieldset class="grid grid-cols-2 gap-2">
                <legend class="sr-only">{{ t('common.status') }}</legend>
                <label v-for="s in ['approved', 'rejected'] as const" :key="s" :class="['flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 text-sm', form.status === s ? (s === 'approved' ? 'border-primary bg-primary/5' : 'border-destructive bg-destructive/5') : 'border-border']">
                    <input v-model="form.status" type="radio" :value="s" class="kt-radio kt-radio-sm" />
                    {{ t(`traders.status_${s}`) }}
                </label>
            </fieldset>
            <label v-if="form.status === 'approved'" class="flex flex-col gap-1.5">
                <span class="text-sm font-medium text-mono">{{ t('traders.tier') }}</span>
                <select v-model="form.price_tier_id" class="kt-select" required>
                    <option v-for="tier in tiers" :key="tier.value" :value="tier.value">{{ tier.label }}</option>
                </select>
                <span v-if="form.errors.price_tier_id" class="text-xs text-destructive">{{ form.errors.price_tier_id }}</span>
            </label>
        </form>
        <template #footer>
            <div class="flex w-full justify-end gap-2.5">
                <button type="button" class="kt-btn kt-btn-outline" @click="emit('close')">{{ t('common.cancel') }}</button>
                <KtButton type="submit" form="trader-review" :loading="form.processing" :variant="form.status === 'rejected' ? 'destructive' : 'primary'">
                    {{ form.status === 'rejected' ? t('traders.reject') : t('traders.approve') }}
                </KtButton>
            </div>
        </template>
    </KtModal>
</template>
