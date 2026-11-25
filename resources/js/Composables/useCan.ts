import { usePage } from '@inertiajs/vue3';
import type { SharedProps } from '@/types';

/**
 * Hides buttons the user may not use. UX only — every action is authorized again on the server.
 * can('products.update'), canAny(['roles.create', 'roles.update']).
 */
export function useCan() {
    const page = usePage<SharedProps>();
    const can = (permission: string): boolean => page.props.auth.permissions.includes(permission);
    const canAny = (permissions: string[]): boolean => permissions.some(can);

    return { can, canAny };
}
