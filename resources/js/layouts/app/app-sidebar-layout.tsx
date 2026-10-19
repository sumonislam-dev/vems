import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { ConfirmDialogHost } from '@/components/confirm-dialog';
import FlashMessages from '@/components/flash-messages';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren } from 'react';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div className="px-6 py-4">{children}</div>
            </AppContent>
            <FlashMessages />
            <ConfirmDialogHost />
        </AppShell>
    );
}
