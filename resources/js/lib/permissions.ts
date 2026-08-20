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
