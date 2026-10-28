import { Button } from '@/components/ui/button';
import { canEditTrip } from '@/lib/trip-status';
import { Trip } from '@/types';
import { router } from '@inertiajs/react';
import { ArrowLeft, Edit } from 'lucide-react';

interface TripActionButtonsProps {
    trip: Trip;
    /** edit-trips */
    canEdit: boolean;
}

/** Page-header buttons; the lifecycle actions (start, complete...) live in the Actions card */
export function TripActionButtons({ trip, canEdit }: TripActionButtonsProps) {
    return (
        <div className="flex items-center gap-2">
            {canEdit && canEditTrip(trip.status) && (
                <Button variant="default" onClick={() => router.visit(route('trips.edit', trip.id))}>
                    <Edit className="mr-2 h-4 w-4" />
                    Edit Trip
                </Button>
            )}
            <Button variant="outline" onClick={() => router.visit(route('trips.index'))}>
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back
            </Button>
        </div>
    );
}
