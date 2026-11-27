<script setup lang="ts">
import FormField from '@/Components/Forms/FormField.vue';
import { useTrans } from '@/Composables/useTrans';

/** Sender, store inboxes for new orders, and which emails go out (Settings → Email). */
const form = defineModel<Record<string, any>>({ required: true });
defineProps<{ errors: Record<string, string>; siteName: string; defaultFrom: string }>();
const { t } = useTrans();

const switches = ['notify_new_order', 'customer_confirmation', 'customer_status_updates'] as const;
</script>

<template>
    <div class="grid gap-5 md:grid-cols-2">
        <FormField id="from_name" :label="t('admin.mail_from_name')" :help="t('admin.mail_default_hint', { value: siteName })" error-key="from_name">
            <input id="from_name" v-model="form.from_name" class="kt-input" maxlength="120" :placeholder="siteName" />
        </FormField>
        <FormField id="from_address" :label="t('admin.mail_from_address')" :help="t('admin.mail_default_hint', { value: defaultFrom })" error-key="from_address">
            <input id="from_address" v-model="form.from_address" type="email" class="kt-input" dir="ltr" :placeholder="defaultFrom" />
        </FormField>
    </div>

    <FormField id="order_recipients" :label="t('admin.mail_recipients')" :help="t('admin.mail_recipients_help')" error-key="order_recipients">
        <textarea id="order_recipients" v-model="form.order_recipients" class="kt-textarea font-mono text-sm" rows="3" dir="ltr" placeholder="orders@example.com" />
    </FormField>

    <fieldset class="grid gap-3">
        <legend class="mb-2 text-sm font-semibold text-mono">{{ t('admin.mail_which') }}</legend>
        <label v-for="key in switches" :key="key" class="flex cursor-pointer items-start gap-3 rounded-xl border border-border p-4 transition hover:bg-accent/40">
            <input v-model="form[key]" type="checkbox" class="kt-switch mt-0.5" />
            <span>
                <span class="block text-sm font-medium text-mono">{{ t(`admin.mail_${key}`) }}</span>
                <span class="block text-xs text-muted-foreground">{{ t(`admin.mail_${key}_help`) }}</span>
            </span>
        </label>
    </fieldset>
</template>
