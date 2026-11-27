<script setup lang="ts">
import FormField from '@/Components/Forms/FormField.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';

/**
 * Mail server (SMTP) from the admin. The saved password is never sent back: the field stays empty and
 * "leave empty to keep it" applies; a separate checkbox removes it.
 */
const form = defineModel<Record<string, any>>({ required: true });
defineProps<{ errors: Record<string, string>; passwordSet: boolean }>();
const { t } = useTrans();

/** Common providers: one click fills host/port/encryption. */
const presets = [
    { name: 'Gmail', host: 'smtp.gmail.com', port: 587, encryption: 'tls' },
    { name: 'Outlook', host: 'smtp.office365.com', port: 587, encryption: 'tls' },
    { name: 'Hostinger', host: 'smtp.hostinger.com', port: 465, encryption: 'ssl' },
    { name: 'cPanel', host: 'mail.ovaletestore.com', port: 465, encryption: 'ssl' },
];
function usePreset(p: (typeof presets)[number]): void {
    Object.assign(form.value, { smtp_host: p.host, smtp_port: p.port, smtp_encryption: p.encryption });
}
</script>

<template>
    <div class="grid gap-5 rounded-xl border border-border p-5">
        <label class="flex cursor-pointer items-start gap-3">
            <input v-model="form.smtp_enabled" type="checkbox" class="kt-switch mt-0.5" />
            <span>
                <span class="block text-sm font-semibold text-mono">{{ t('admin.smtp_enabled') }}</span>
                <span class="block text-xs text-muted-foreground">{{ t('admin.smtp_enabled_help') }}</span>
            </span>
        </label>

        <template v-if="form.smtp_enabled">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="text-muted-foreground">{{ t('admin.smtp_presets') }}</span>
                <button v-for="p in presets" :key="p.name" type="button" class="kt-btn kt-btn-sm kt-btn-outline" @click="usePreset(p)">{{ p.name }}</button>
            </div>

            <div class="grid gap-5 md:grid-cols-4">
                <FormField id="smtp_host" class="md:col-span-2" :label="t('admin.smtp_host')" error-key="smtp_host" required>
                    <input id="smtp_host" v-model="form.smtp_host" class="kt-input" dir="ltr" placeholder="smtp.example.com" autocomplete="off" />
                </FormField>
                <FormField id="smtp_port" :label="t('admin.smtp_port')" error-key="smtp_port" required>
                    <input id="smtp_port" v-model.number="form.smtp_port" type="number" min="1" max="65535" class="kt-input" dir="ltr" />
                </FormField>
                <FormField id="smtp_encryption" :label="t('admin.smtp_encryption')" error-key="smtp_encryption">
                    <select id="smtp_encryption" v-model="form.smtp_encryption" class="kt-select" dir="ltr">
                        <option value="tls">TLS (587)</option>
                        <option value="ssl">SSL (465)</option>
                        <option value="none">{{ t('admin.smtp_none') }}</option>
                    </select>
                </FormField>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <FormField id="smtp_username" :label="t('admin.smtp_username')" error-key="smtp_username">
                    <input id="smtp_username" v-model="form.smtp_username" class="kt-input" dir="ltr" autocomplete="off" />
                </FormField>
                <FormField
                    id="smtp_password"
                    :label="t('admin.smtp_password')"
                    :help="passwordSet ? t('admin.smtp_password_keep') : t('admin.smtp_password_help')"
                    error-key="smtp_password"
                >
                    <input id="smtp_password" v-model="form.smtp_password" type="password" class="kt-input" dir="ltr" autocomplete="new-password" :placeholder="passwordSet ? '••••••••' : ''" />
                </FormField>
            </div>
            <label v-if="passwordSet" class="flex items-center gap-2 text-xs text-muted-foreground">
                <input v-model="form.smtp_password_clear" type="checkbox" class="kt-checkbox" />{{ t('admin.smtp_password_clear') }}
            </label>
            <p class="flex items-start gap-2 text-xs text-muted-foreground">
                <KtIcon name="shield-tick" class="mt-0.5 text-primary" />{{ t('admin.smtp_security_note') }}
            </p>
        </template>
    </div>
</template>
