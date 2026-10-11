import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import { XCircle } from 'lucide-react';
import { FormEvent, useState } from 'react';

/**
 * "Reject Trip" button plus a dialog asking for the (required) reason.
 */
export function TripRejectDialog({ tripId }: { tripId: number }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ rejection_reason: '' });

    const close = () => {
        setOpen(false);
        reset();
        clearErrors();
    };

    const submit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(route('trips.reject', tripId), { preserveScroll: true, onSuccess: close });
    };

    return (
        <>
            <Button variant="outline" className="w-full" onClick={() => setOpen(true)}>
                <XCircle className="mr-2 h-4 w-4" />
                Reject Trip
            </Button>

            <Dialog open={open} onOpenChange={(isOpen) => !isOpen && close()}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Reject trip</DialogTitle>
                        <DialogDescription>The requester will see this reason.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="space-y-1">
                            <Label htmlFor="rejection_reason">Reason</Label>
                            <Textarea
                                id="rejection_reason"
                                value={data.rejection_reason}
                                onChange={(e) => setData('rejection_reason', e.target.value)}
                                rows={3}
                                required
                                autoFocus
                            />
                            <InputError message={errors.rejection_reason} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={close} disabled={processing}>
                                Cancel
                            </Button>
                            <Button type="submit" variant="destructive" disabled={processing || !data.rejection_reason.trim()}>
                                Reject
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
