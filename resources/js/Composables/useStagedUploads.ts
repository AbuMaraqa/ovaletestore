import { computed, onBeforeUnmount, reactive } from 'vue';
import { useTrans } from '@/Composables/useTrans';

/**
 * Images picked on a product form are uploaded one by one right away (progress, per-file error) to
 * POST admin/uploads; the form then only sends the returned ids, in the order shown. See App\Support\StagedUploads.
 * Previews are blob: URLs (allowed by the CSP img-src), revoked when removed or when the page unmounts.
 */
export interface StagedImage {
    key: string;
    name: string;
    preview: string;
    progress: number;
    status: 'uploading' | 'done' | 'error';
    id?: string;
    error?: string;
}

export const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];
export const MAX_MB = 10;

function xsrf(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export function useStagedUploads(onChange: (ids: string[]) => void, max: () => number) {
    const { t } = useTrans();
    const items = reactive<StagedImage[]>([]);
    const ids = () => items.filter((i) => i.status === 'done' && i.id).map((i) => i.id!);
    const emit = () => onChange(ids());

    function upload(item: StagedImage, file: File): void {
        const body = new FormData();
        body.append('file', file);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', route('admin.uploads.store'));
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-XSRF-TOKEN', xsrf());
        xhr.upload.onprogress = (e) => e.lengthComputable && (item.progress = Math.round((e.loaded / e.total) * 100));
        xhr.onload = () => {
            const data = (() => {
                try {
                    return JSON.parse(xhr.responseText);
                } catch {
                    return {};
                }
            })();
            if (xhr.status === 201 && data.id) {
                Object.assign(item, { status: 'done', id: data.id, progress: 100 });
                emit();
            } else {
                Object.assign(item, { status: 'error', error: data.errors?.file?.[0] ?? data.message ?? t('forms.upload_failed') });
            }
        };
        xhr.onerror = () => Object.assign(item, { status: 'error', error: t('forms.upload_failed') });
        xhr.send(body);
    }

    /** Adds files (picker or drop): checked here first (type, size, count), then uploaded. */
    function add(files: FileList | File[]): void {
        for (const file of Array.from(files)) {
            if (items.filter((i) => i.status !== 'error').length >= max()) break;
            const item = reactive<StagedImage>({ key: crypto.randomUUID(), name: file.name, preview: URL.createObjectURL(file), progress: 0, status: 'uploading' });
            items.push(item);
            if (!ACCEPTED.includes(file.type)) Object.assign(item, { status: 'error', error: t('forms.image_type') });
            else if (file.size > MAX_MB * 1024 * 1024) Object.assign(item, { status: 'error', error: t('forms.image_size', { max: MAX_MB }) });
            else upload(item, file);
        }
    }

    function remove(key: string): void {
        const index = items.findIndex((i) => i.key === key);
        if (index < 0) return;
        const [item] = items.splice(index, 1);
        URL.revokeObjectURL(item.preview);
        if (item.id) {
            void fetch(route('admin.uploads.destroy', item.id), { method: 'DELETE', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() } });
        }
        emit();
    }

    function move(from: number, to: number): void {
        if (to < 0 || to >= items.length || from === to) return;
        const [item] = items.splice(from, 1);
        items.splice(to, 0, item);
        emit();
    }

    /** After a successful save the staged files belong to the product: forget them here. */
    function reset(): void {
        items.splice(0).forEach((i) => URL.revokeObjectURL(i.preview));
    }

    onBeforeUnmount(() => items.forEach((i) => URL.revokeObjectURL(i.preview)));

    return { items, add, remove, move, reset, busy: computed(() => items.some((i) => i.status === 'uploading')) };
}
