import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Permisos del usuario en la sede activa. Solo para mostrar u ocultar elementos:
 * el servidor vuelve a autorizar cada operación.
 */
export function usePermissions() {
    const page = usePage();
    const permissions = computed(() => new Set(page.props.auth.permissions));

    const can = (permission: string): boolean =>
        permissions.value.has(permission);

    return { can };
}
