import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useEffect, useState } from 'react';

interface ConfirmOptions {
    title: string;
    description?: string;
    confirmLabel?: string;
    destructive?: boolean;
}

type Opener = (options: ConfirmOptions, resolve: (confirmed: boolean) => void) => void;

// Set by the mounted <ConfirmDialogHost /> (in AppSidebarLayout). A module-level
// opener rather than React context, because pages call this from code that
// renders the layout, i.e. outside any provider the layout could offer.
let openDialog: Opener | null = null;

/**
 * Styled replacement for window.confirm(): resolves true when the user confirms.
 */
export function confirmAction(options: ConfirmOptions): Promise<boolean> {
    return new Promise((resolve) => {
        if (!openDialog) {
            resolve(window.confirm(options.description ?? options.title));
            return;
        }
        openDialog(options, resolve);
    });
}

export function confirmDelete(description: string, confirmLabel = 'Delete'): Promise<boolean> {
    return confirmAction({ title: 'Please confirm', description, confirmLabel, destructive: true });
}

export function ConfirmDialogHost() {
    const [request, setRequest] = useState<{ options: ConfirmOptions; resolve: (confirmed: boolean) => void } | null>(null);

    useEffect(() => {
        openDialog = (options, resolve) => setRequest({ options, resolve });
        return () => {
            openDialog = null;
        };
    }, []);

    const close = (confirmed: boolean) => {
        request?.resolve(confirmed);
        setRequest(null);
    };

    return (
        <Dialog open={request !== null} onOpenChange={(isOpen) => !isOpen && close(false)}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{request?.options.title}</DialogTitle>
                    {request?.options.description && <DialogDescription>{request.options.description}</DialogDescription>}
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={() => close(false)}>
                        Cancel
                    </Button>
                    <Button variant={request?.options.destructive ? 'destructive' : 'default'} onClick={() => close(true)} autoFocus>
                        {request?.options.confirmLabel ?? 'Confirm'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
