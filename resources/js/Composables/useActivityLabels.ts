import { useTrans } from '@/Composables/useTrans';

const TONES: Record<string, { icon: string; badge: string }> = {
    created: { icon: 'plus', badge: 'border-green-500/30 bg-green-500/10 text-green-600 dark:text-green-400' },
    updated: { icon: 'notepad-edit', badge: 'border-primary/30 bg-primary/10 text-primary' },
    deleted: { icon: 'trash', badge: 'border-destructive/30 bg-destructive/10 text-destructive' },
    restored: { icon: 'arrows-loop', badge: 'border-green-500/30 bg-green-500/10 text-green-600 dark:text-green-400' },
    login: { icon: 'entrance-left', badge: 'border-sky-500/30 bg-sky-500/10 text-sky-600 dark:text-sky-400' },
    logout: { icon: 'exit-right', badge: 'border-border bg-muted text-muted-foreground' },
    login_failed: { icon: 'shield-cross', badge: 'border-destructive/30 bg-destructive/10 text-destructive' },
    password_reset: { icon: 'key', badge: 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400' },
    roles_changed: { icon: 'security-user', badge: 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400' },
};

/** Translated names for audit events, record types and fields (raw name when no translation exists). */
export function useActivityLabels() {
    const { t } = useTrans();
    const tr = (key: string, fallback: string) => {
        const text = t(key);
        return text === key ? fallback : text;
    };

    return {
        eventLabel: (event: string) => tr(`activity.events.${event}`, event),
        eventTone: (event: string) => TONES[event] ?? { icon: 'information-2', badge: 'border-border bg-muted text-secondary-foreground' },
        subjectLabel: (subject: string) => tr(`activity.subjects.${subject}`, subject),
        logLabel: (log: string) => tr(`activity.logs.${log}`, log),
        fieldLabel: (field: string) => tr(`activity.fields.${field}`, field.replace(/_/g, ' ')),
        value: (v: string | boolean | number | null | undefined) =>
            v === null || v === undefined || v === '' ? '—' : typeof v === 'boolean' ? (v ? t('common.yes') : t('common.no')) : String(v),
    };
}
