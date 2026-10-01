import { FormMultiSelect } from '@/base-components/base-form';

const ALL_ZONES_VALUE = 'all';

interface ZoneAccessSelectProps {
    zoneAccess: 'all' | 'specific';
    zoneIds: string[];
    onChange: (zoneAccess: 'all' | 'specific', zoneIds: string[]) => void;
    zoneOptions: Array<{ label: string; value: string }>;
    error?: string;
}

/**
 * A single dropdown for zone access: pick one or more specific zones, or
 * "All Zones" for unrestricted access. Picking "All Zones" is exclusive —
 * it replaces any specific selections — and picking a specific zone while
 * "All Zones" is selected drops it. Admins/Super Admins get full zone
 * access via their role regardless of this setting.
 */
export function ZoneAccessSelect({ zoneAccess, zoneIds, onChange, zoneOptions, error }: ZoneAccessSelectProps) {
    const options = [{ label: 'All Zones', value: ALL_ZONES_VALUE }, ...zoneOptions];
    const value = zoneAccess === 'all' ? [ALL_ZONES_VALUE] : zoneIds;

    const handleChange = (values: string[]) => {
        const hasAll = values.includes(ALL_ZONES_VALUE);
        const hadAllBefore = value.includes(ALL_ZONES_VALUE);

        if (hasAll && (!hadAllBefore || values.length === 1)) {
            onChange('all', []);
            return;
        }

        onChange(
            'specific',
            values.filter((v) => v !== ALL_ZONES_VALUE),
        );
    };

    return (
        <FormMultiSelect
            label="Zone Access"
            name="zone_ids"
            value={value}
            onChange={handleChange}
            error={error}
            options={options}
            placeholder="Select zone access..."
            searchable={false}
            description="Admins/Super Admins always have full zone access regardless of this setting."
        />
    );
}
