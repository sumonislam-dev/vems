import { Button } from '@/components/ui/button';
import { Trip } from '@/types';
import { ArrowLeft, Edit, Play } from 'lucide-react';
import { router } from '@inertiajs/react';

interface TripActionButtonsProps {
    trip: Trip;
    onOpenStartTrip: () => void;
    /** edit-trips */
    canEdit: boolean;
    /** Status allows starting and the user may start it (see trips/show.tsx) */
    canStart: boolean;
}

export function TripActionButtons({ trip, onOpenStartTrip, canEdit, canStart }: TripActionButtonsProps) {
    return (
        <div className="flex items-center gap-2">
            {canEdit && ['pending', 'approved'].includes(trip.status) && (
                <Button variant="default" onClick={() => router.visit(route('trips.edit', trip.id))}>
                    <Edit className="mr-2 h-4 w-4" />
                    Edit Trip
                </Button>
            )}
            {canStart && (
                <Button className="bg-green-600 hover:bg-green-700" onClick={onOpenStartTrip}>
                    <Play className="mr-2 h-4 w-4" />
                    Start Trip
                </Button>
            )}
            <Button variant="outline" onClick={() => router.visit(route('trips.index'))}>
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back
            </Button>
        </div>
    );
}
