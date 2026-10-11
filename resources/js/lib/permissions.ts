import { usePage } from '@inertiajs/react';

/**
 * Check whether the current user's permission list satisfies a requirement.
 * A string[] requirement is satisfied if the user has ANY one of them
 * (mirrors Laravel's `permission:a|b` middleware syntax).
 */
export function hasPermission(userPermissions: string[] = [], required?: string | string[]): boolean {
    if (!required) return true;

    const requiredList = Array.isArray(required) ? required : [required];
    return requiredList.some((permission) => userPermissions.includes(permission));
}

/**
 * Hook form of hasPermission() bound to the signed-in user's permissions
 * (shared by HandleInertiaRequests as auth.permissions):
 *   const can = useCan();
 *   {can('edit-vendors') && <EditButton />}
 */
export function useCan(): (required?: string | string[]) => boolean {
    const { auth } = usePage<{ auth?: { permissions?: string[] } }>().props;
    const permissions = auth?.permissions ?? [];

    return (required) => hasPermission(permissions, required);
}
