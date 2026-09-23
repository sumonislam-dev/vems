import { LucideIcon } from 'lucide-react';
import type { Config } from 'ziggy-js';
import type { User } from './user';

export interface Auth {
    user: User;
    permissions?: string[];
    roles?: string[];
}

export interface NotificationItem {
    id: string;
    message: string;
    url: string | null;
    read: boolean;
    time: string;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    children?: NavItem[];
    /** Permission name(s) required to show this item. An array means "any of". Omit to always show. */
    permission?: string | string[];
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    notifications: { unread_count: number; items: NotificationItem[] };
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    flash: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
    [key: string]: unknown;
}
