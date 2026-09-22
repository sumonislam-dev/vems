import type { Role, User } from './user';

export interface Permission {
    id: number;
    name: string;
    guard_name: string;
    created_at: string;
    updated_at: string;
    roles?: Role[];
    users?: User[];
    users_count?: number;
    roles_count?: number;
}
