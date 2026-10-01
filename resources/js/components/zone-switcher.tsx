import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Check, ChevronDown, MapPin } from 'lucide-react';

/**
 * Lets a user with access to more than one zone temporarily narrow their
 * own view to a single zone — a viewing filter on top of their real
 * access, never a way to see more than that access allows (see
 * Zone::accessIds()). Renders nothing for anyone with one zone or fewer,
 * since there's nothing to switch between.
 */
export function ZoneSwitcher() {
    const { auth } = usePage<SharedData>().props;
    const zones = auth.availableZones ?? [];

    if (zones.length === 0) {
        return null;
    }

    const activeZone = zones.find((zone) => zone.id === auth.activeZoneId);

    const switchZone = (zoneId: number | null) => {
        router.patch(route('active-zone.update'), { zone_id: zoneId }, { preserveScroll: true });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="sm" className="gap-1.5">
                    <MapPin className="h-4 w-4" />
                    <span className="hidden sm:inline">{activeZone ? activeZone.name : 'All Zones'}</span>
                    <ChevronDown className="h-3 w-3 opacity-50" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuLabel>Viewing zone</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem onClick={() => switchZone(null)}>
                    <Check className={`mr-2 h-4 w-4 ${auth.activeZoneId ? 'invisible' : ''}`} />
                    All Zones
                </DropdownMenuItem>
                {zones.map((zone) => (
                    <DropdownMenuItem key={zone.id} onClick={() => switchZone(zone.id)}>
                        <Check className={`mr-2 h-4 w-4 ${auth.activeZoneId === zone.id ? '' : 'invisible'}`} />
                        {zone.name}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
