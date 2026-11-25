<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import KtButton from '@/Components/UI/KtButton.vue';
import KtCard from '@/Components/UI/KtCard.vue';
import KtIcon from '@/Components/UI/KtIcon.vue';
import { useTrans } from '@/Composables/useTrans';

/** Where mail really goes out (read-only, from .env) + an instant test message. */
defineProps<{ transport: { mailer: string; host: string | null; default_from: string; delivers: boolean } }>();
const { t } = useTrans();
const form = useForm({ email: '' });
const send = () => form.post(route('admin.settings.mail.test'), { preserveScroll: true });
</script>

<template>
    <KtCard :title="t('admin.mail_server')" class="mt-6 max-w-4xl">
        <div class="grid gap-5 md:grid-cols-2">
            <div class="space-y-3 text-sm">
                <div :class="['flex items-start gap-3 rounded-xl p-4', transport.delivers ? 'bg-green-500/10 text-green-700 dark:text-green-300' : 'bg-amber-500/10 text-amber-700 dark:text-amber-300']">
                    <KtIcon :name="transport.delivers ? 'check-circle' : 'information-2'" class="mt-0.5 text-lg" />
                    <p>{{ transport.delivers ? t('admin.mail_live', { mailer: transport.mailer.toUpperCase(), host: transport.host ?? '' }) : t('admin.mail_not_live', { mailer: transport.mailer }) }}</p>
                </div>
                <p class="text-xs text-muted-foreground">{{ t('admin.mail_server_help') }}</p>
            </div>
            <form class="flex flex-col gap-2" @submit.prevent="send">
                <label for="test-email" class="text-sm font-medium text-mono">{{ t('admin.mail_test') }}</label>
                <div class="flex gap-2">
                    <input id="test-email" v-model="form.email" type="email" required class="kt-input grow" dir="ltr" placeholder="you@example.com" />
                    <KtButton type="submit" :loading="form.processing" icon="sms">{{ t('admin.mail_test_send') }}</KtButton>
                </div>
                <p v-if="!transport.delivers" class="text-xs text-amber-700 dark:text-amber-300">{{ t('admin.mail_test_logged_only') }}</p>
                <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
            </form>
        </div>
    </KtCard>
</template>
